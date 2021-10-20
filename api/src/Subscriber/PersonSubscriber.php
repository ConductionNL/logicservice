<?php


namespace App\Subscriber;


use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Person;
use App\Service\PersonService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class PersonSubscriber implements EventSubscriberInterface
{
    private SerializerService $serializerService;
    private PersonService $personService;
    private ParameterBagInterface $parameterBag;

    public function __construct(SerializerInterface $serializer, CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag)
    {
        $this->personService = new PersonService($commonGroundService, $entityManager);
        $this->serializerService = new SerializerService($serializer);
        $this->parameterBag = $parameterBag;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['checkPerson', EventPriorities::PRE_WRITE],
        ];
    }

    public function checkPerson(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');
        $resource = $event->getControllerResult();

        if($route != 'api_people_post_collection' || !($resource instanceof Person)){
            return;
        }
        $this->serializerService->setResponse($this->personService->checkPerson($resource, $this->parameterBag->get('app_mode')), $event, ['ignore_attributes' => ['id']]);
    }

}