<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Service\CalculateCarbone;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Service\CheckReservation;
use App\Repository\VehiculeRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/vehicule', name: 'vehicule_')]
class VehiculeController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(VehiculeRepository $vehiculeRepository): Response
    {
        $vehicules = $vehiculeRepository->findAll();

        return $this->render('vehicule/index.html.twig', [
            'vehicules' => $vehicules,
        ]);
    }

    #[Route('/show/{id}', requirements: ['id' => '\d+'], name: 'show')]
    public function show(Vehicule $vehicule, ReservationRepository $reservationRepository, CalculateCarbone $calculateCarbonne): Response
    {

        return $this->render('vehicule/show.html.twig', [
            'vehicule' => $vehicule,
            'calculateCarbonne' => $calculateCarbonne->calculate($vehicule),
            'carbonneBykm' => $calculateCarbonne->calculateByKm($vehicule)
        ]);
    }

    #[Route('/show/{id}/reservation', name: 'reservation')]
    public function reservation(Request $request, ReservationRepository $reservationRepository, VehiculeRepository $vehiculeRepository, UserRepository $userRepository): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        $vehicule = $vehiculeRepository->findOneBy(['id' => $reservation->getCar()]);
        $user = $userRepository->findOneBy(['id' => $reservation->getDriver()]);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehicule->setIsReserved(true);
            $user->setHasReserved(true);
            $reservationRepository->save($reservation, true);
            $vehiculeRepository->save($vehicule, true);
            $userRepository->save($user, true);

            return $this->redirectToRoute('vehicule_reservation_confirmation', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('reservation/index.html.twig', [
            'reservation' => $reservation,
            'form' => $form
        ]);
    }

    #[Route('/show/{id}/confirmation', name: 'reservation_confirmation')]
    public function confirmation(ReservationRepository $reservationRepository, int $id): Response
    {
        $reservation = $reservationRepository->findOneBy(['id' => $id]);

        return $this->render('reservation/result.html.twig', [
            'reservation' => $reservation
        ]);
    }

    #[Route('/bookings', name: 'bookings')]
    public function booking(ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneBy(['driver' => $this->getUser()]);

        return $this->render('reservation/bookings.html.twig', [
            'reservation' => $reservation
        ]);
    }

    #[Route('/{id}', name: 'reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Vehicule $vehicule, VehiculeRepository $vehiculeRepository, UserRepository $userRepository, Reservation $reservation): Response
    {
        $user = $userRepository->findOneBy(['id' => $reservation->getDriver()]);

        if ($this->isCsrfTokenValid('delete'.$vehicule->getId(), $request->request->get('_token'))) {
            $vehiculeRepository->remove($vehicule, true);
            $user->setHasReserved(false);
            $userRepository->save($user, true);
        }

        return $this->redirectToRoute('vehicule_bookings', [], Response::HTTP_SEE_OTHER);
    }
}
