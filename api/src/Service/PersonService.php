<?php


namespace App\Service;


use App\Entity\Person;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;

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

    public function getCoMovers(array $person): array
    {
        if(!$this->checkAge($person, 18)){
            return [];
        }
        $coMovers = [];
        if($relatives = $this->getRelatives($person)){
            $coMovers = $this->commonGroundService->getResourceList(['component' => 'brp', 'type' => 'ingeschrevenpersonen'], $this->getQuery($person, $relatives));
        }
        if(isset($coMovers['_embedded']['ingeschrevenpersonen'])){
            $coMovers = $coMovers['_embedded']['ingeschrevenpersonen'];
        }
        return $coMovers;
    }

    public function checkPerson(Person $person): Person
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
        $person->setCoMovers($this->getCoMovers($personArray));

        $this->entityManager->persist($person);
        return $person;
    }

}