<?php


namespace App\Service;


use App\Entity\Person;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class PersonService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
    }

    public function checkAlive(array $person): bool
    {
        if(isset($person['overlijden']['indicatieOverleden']) && $person['overlijden']['indicatieOverleden']){
            return false;
        }
        return true;
    }

    public function checkAge(array $person, int $age): bool
    {
        if(isset($person['leeftijd']) && $person['leeftijd'] >= $age){
            return true;
        }
        return false;
    }

    public function checkUnderWard(array $person)
    {
        if(isset($person['gezagsverhouding']['indicatieCurateleRegister']) && $person['gezagsverhouding']['indicatieCurateleRegister']){
            return false;
        }
        return true;
    }

    public function checkIsEligible(array $person): bool
    {
        return (
            $this->checkAlive($person) &&
            $this->checkAge($person, 16) &&
            $this->checkUnderWard($person)
        );
    }

    public function getKinderen(array $person, array $result): array
    {
        if(isset($person['_embedded']['kinderen'])){
            foreach($person['_embedded']['kinderen'] as $kind){
                isset($kind['burgerservicenummer']) ? $result[] = $kind['burgerservicenummer'] : null;
            }
        } elseif(isset($person['kinderen'])) {
            foreach($person['kinderen'] as $kind){
                isset($kind['bsn']) ? $result[] = $kind['bsn'] : null;
            }
        }
        return $result;
    }

    public function getOuders(array $person, array $result): array
    {
        if(isset($person['_embedded']['ouders'])) {
            foreach ($person['_embedded']['ouders'] as $ouder) {
                isset($ouder['burgerservicenummer']) ? $result[] = $ouder['burgerservicenummer'] : null;
            }
        } elseif(isset($person['ouders'])) {
            foreach($person['ouders'] as $ouder){
                isset($ouder['bsn']) ? $result[] = $ouder['bsn'] : null;
            }
        }
        return $result;
    }

    public function getPartners(array $person, array $result): array
    {
        if(isset($person['_embedded']['partners'])){
            foreach($person['_embedded']['partners'] as $partner){
                isset($partner['burgerservicenummer']) ? $result[] = $partner['burgerservicenummer'] : null;
            }
        } elseif(isset($person['partners'])) {
            foreach($person['partners'] as $partner){
                isset($partner['bsn']) ? $result[] = $partner['bsn'] : null;
            }
        }
        return $result;
    }

    public function getRelatives(array $person): array
    {
        $relatives = [];
        $relatives = $this->getKinderen($person, $relatives);
        $relatives = $this->getOuders($person, $relatives);
        $relatives = $this->getPartners($person, $relatives);
        return $relatives;
    }

    public function getQuery(array $person, array $relatives): array
    {
            $query = ['burgerservicenummer' => implode(',', $relatives), ];
//        if(isset($person['verblijfplaats']['nummeraanduidingIdentificatie'])){
//            $query['verblijfplaats__nummeraanduidingIdentificatie'] = $person['verblijfplaats']['nummeraanduidingIdentificatie'];
//        } else {
            isset($person['verblijfplaats']['postcode']) ? $query['verblijfplaats__postcode'] = $person['verblijfplaats']['postcode'] : null;
            isset($person['verblijfplaats']['huisnummer']) ? $query['verblijfplaats__huisnummer'] = $person['verblijfplaats']['huisnummer'] : null;
            isset($person['verblijfplaats']['huisnummertoevoeging']) ? $query['verblijfplaats__huisnummertoevoeging'] = $person['verblijfplaats']['huisnummertoevoeging'] : null;
            isset($person['verblijfplaats']['huisletter']) ? $query['verblijfplaats__huisletter'] = $person['verblijfplaats']['huisletter'] : null;
//        }

        return $query;
    }

    public function matchAddress(...$people): array
    {
        $address = array();
        foreach($people as $key=>$person){
            if(is_array($person) && !$address){
                $address = $person['verblijfplaats'];
            } elseif(is_array($person) && !isset($person['verblijfplaats']['nummeraanduidingIdentificatie']) || !isset($address['nummeraanduidingIdentificatie'])) {
                $result = true;
                (!isset($person['verblijfplaats']['postcode']) || !isset($address['postcode']) || $person['verblijfplaats']['postcode'] !== $address['postcode']) ? $result = false : null;
                (!isset($person['verblijfplaats']['huisnummer']) || !isset($address['huisnummer']) || $person['verblijfplaats']['huisnummer'] !== $address['huisnummer']) ? $result = false : null;
                (isset($person['verblijfplaats']['huisnummerToevoeging']) xor isset($address['huisnummerToevoeging']) || (isset($address['huisnummerToevoeging']) && isset($person['verblijfplaats']['huisnummerToevoeging']) && $address['huisnummerToevoeging'] !== $person['verblijfplaats']['huisnummerToevoeging'])) ? $result = false : null;
                (isset($person['verblijfplaats']['huisletter']) xor isset($address['huisletter']) || (isset($address['huisletter']) && isset($person['verblijfplaats']['huisletter']) && $address['huisletter'] !== $person['verblijfplaats']['huisletter'])) ? $result = false : null;
                if(!$result){
                    unset($people[$key]);
                }
                continue;
            } elseif(is_array($person)) {
                $result = true;
                $person['verblijfplaats']['nummeraanduidingIdentificatie'] !== $address['nummeraanduidingIdentificatie'] ? $result = false : null;
                if(!$result){
                    unset($people[$key]);
                }
                continue;
            }
        }
        return $people;
    }

    public function getCoMoversPerBSN(array $person, array $relatives): array
    {
        $coMovers = array();
        $promises = array();
        foreach($relatives as $relative){
            $promises[] = $this->commonGroundService->getResource(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $relative], [], true, true);
        }

        $responses = Utils::settle($promises)->wait();
        foreach($responses as $response){
            if($response instanceof ResponseInterface){
                $coMovers = json_decode($response->getBody()->getContents(), true);
            }
        }
        $coMovers = $this->matchAddress(array_unshift($coMovers, $person));
        return $coMovers;
    }

    public function getCoMovers(array $person, $type): array
    {
        if(!$this->checkAge($person, 18)){
            return [];
        }
        $coMovers = [];
        $relatives = $this->getRelatives($person);
        if($relatives && $type == 'vrijbrp'){
            $coMovers = $this->commonGroundService->getResourceList(['component' => 'brp', 'type' => 'ingeschrevenpersonen'], $this->getQuery($person, $relatives));
            if(isset($coMovers['_embedded']['ingeschrevenpersonen'])){
                $coMovers = $coMovers['_embedded']['ingeschrevenpersonen'];
            }
        } elseif ($relatives && $type == 'servicegateway') {
            $this->getCoMoversPerBSN($person, $relatives);
        }
        return $coMovers;
    }

    public function checkPerson(Person $person, string $type): Person
    {
        try{
            $personArray = $this->commonGroundService->getResource(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $person->getBrp()], ['geefFamilie' => 'true']);
        } catch(ClientException $e){
            try{
                $personArray = $this->commonGroundService->getResource(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $person->getBrp()], ['expand' => 'ouders,kinderen,partners']);
            } catch(ClientException $e){
                $personArray = $this->commonGroundService->getResource(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $person->getBrp()]);
            }
        }

        $person->setIsEligible($this->checkIsEligible($personArray));
        $person->setCoMovers($this->getCoMovers($personArray, $type));

        $this->entityManager->persist($person);
        return $person;
    }

}