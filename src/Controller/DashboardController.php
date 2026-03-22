<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function index(
        EventRepository $eventRepo,
        ReservationRepository $reservationRepo
    ): Response {
        $events = $eventRepo->findAll();
        $totalReservations = count($reservationRepo->findAll());

        return $this->render('admin/dashboard.html.twig', [
            'events'            => $events,
            'total_events'      => count($events),
            'total_reservations' => $totalReservations,
        ]);
    }
}
