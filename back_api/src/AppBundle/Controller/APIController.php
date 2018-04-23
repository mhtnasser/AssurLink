<?php
/**
 * Created by PhpStorm.
 * User: kevinmouga
 * Date: 30/01/2018
 * Time: 14:54
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Device;
use AppBundle\Entity\User;
use AppBundle\Entity\UserDevice;
use FOS\RestBundle\FOSRestBundle;
use FOS\RestBundle\Controller\Annotations\Route;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\View;

use FOS\RestBundle\Controller\Annotations as Rest; // alias pour toutes les annotations

class APIController extends FOSRestBundle
{
    /**
     * @Rest\View()
     * @Rest\Post("/userInfo")
     *
     * @param Request $request
     * @return User
     *
     */
    public function getUserAction(Request $request)
    {
        $logger = $this->container->get('logger');
        $login = $request->request->get('login');
        $password = $request->request->get('password');

        if (empty($login) || empty($password)){
            return new JsonResponse(['message' => 'parameters missing'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->container->get('doctrine.orm.entity_manager')
            ->getRepository('AppBundle:User')
            ->getUser($login, $password);

        if (empty($user)) {
            return new JsonResponse(['message' => 'user not found'], Response::HTTP_NOT_FOUND);
        }
        return $user;
    }


    //todo controlleur qui récupére l'alerte du boitier avec l'id de l'objet connecté
    //fait passé à true tous les UserDevice
    //envoie les scenarios à tous les user qui on l'objet

    /**
     * @Route("/zone", name="zone", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function testAction(Request $request)
    {

        $em = $this->container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository('AppBundle:User');
        $deviceRep = $em->getRepository('AppBundle:Device');
        //$zoneRepository = $em->getRepository('AppBundle:Zone');
        //$zone = $zoneRepository->find(2);
        $users = $userRepository->getUserWithZone(2);
        $device = [];
        foreach ($users as $user)
        {
            $device[] = $em->getRepository('AppBundle:UserDevice')->findBy(array("user" => $user));
        }
        /*dump("tous les utilisateur de la zone",$users);
        dump("tous les devise d'un user par zone",$device);die();*/
        $data =  $this->container->get('serializer')->serialize($userRepository->find(1), 'json');

        return new JsonResponse(array('object' => $data ));
    }

    /**
     * @Rest\View()
     * @Rest\Post("/user")
     */
    public function userAction(Request $request)
    {
        $logger = $this->container->get('logger');
        $login = $request->request->get('login');
        $password = $request->request->get('password');

        if (empty($login) || empty($password)){
            return new JsonResponse(['message' => 'parameters missing',
                                     'error' => true
            ], Response::HTTP_NOT_FOUND);
        }

        $em = $this->container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository('AppBundle:User');
        $user = $userRepository->getUserAndZone($login, $password);

        if (empty($user)) {
            return new JsonResponse(['message' => 'user not found',
                                     'error' => true
            ], Response::HTTP_NOT_FOUND);
        }

        $devices = $em->getRepository('AppBundle:UserDevice')->findBy(array("user" => $user));
        /** @var UserDevice $device */
        $deviceScene = [];
        foreach ($devices as $device)
        {
           $deviceScene[] = $em->getRepository('AppBundle:DeviceScene')->findBy(array("device" => $device->getDevice()));
        }
        $data = array('user' => $user,'userDevice'=> $devices,'SceneDevice' => $deviceScene);
        //dump($user, $devices, $deviceScene); die();

        return $data;
    }

    /**
     * @Rest\View
     * @Rest\Post("/user-all-devices")
     * @param Request $request
     * @return JsonResponse
     */
    public function UserAllDevicesAction(Request $request)
    {
        $user_id = $request->request->get('user_id');

        $em = $this->container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository('AppBundle:User');
        $user = $userRepository->getUserAndZone($user_id);
        $devices = $em->getRepository('AppBundle:UserDevice')->findBy(array("user" => $user));
        if (empty($devices)) {
            return new JsonResponse(['message' => 'user by device not found'], Response::HTTP_NOT_FOUND);
        }
        return $devices;
    }

    /**
     * @param Request $request
     * @return array|JsonResponse
     * @Rest\View
     * @Rest\Post("/alarm")
     */
    public function setAlarm(Request $request)
    {
        $id = $request->request->get('id');
        $alarm = $request->get("alarm");

        $em = $this->container->get('doctrine.orm.entity_manager');
        $device = $em->getRepository('AppBundle:UserDevice')->find($id);

        if (empty($device)) {
            return new JsonResponse(['message' => 'user device not found'], Response::HTTP_NOT_FOUND);
        }
        /** @var UserDevice $device */
        $this->startOtherDevice($device->getDevice(), (int) $alarm);
        $em->flush();
        return array( 'message' => 'alarm active');
    }

    private function startOtherDevice(Device $device, int $alarm)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $devices = $em->getRepository('AppBundle:UserDevice')->findBy(array('device' => $device));
        foreach ($devices as $device)
        {
            /** @var UserDevice $device */
            $device->setAlert($alarm);
        }
    }
}