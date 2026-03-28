<?php

namespace App\Controller;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ReservationRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
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

        return $this->render('admin/dashboard.html.twig', [
            'events'             => $events,
            'total_events'       => count($events),
            'total_reservations' => count($reservationRepo->findAll()),
        ]);
    }

    #[Route('/reservations', name: 'admin_reservations')]  
    public function reservations(ReservationRepository $repo): Response
    {
        return $this->render('admin/reservations.html.twig', [
            'reservations' => $repo->findAll(),
        ]);
    }

    #[Route('/users', name: 'admin_users')]
public function users(ReservationRepository $repo): Response
{
    return $this->render('admin/users.html.twig', [
        'reservations' => $repo->findAll(),
    ]);
}
#[Route('/users/{id}/edit', name: 'admin_user_edit')]
public function userEdit(int $id, ReservationRepository $repo, Request $request, EntityManagerInterface $em): Response
{
    $reservation = $repo->find($id);
    if (!$reservation) throw $this->createNotFoundException();

    if ($request->isMethod('POST')) {
        $reservation->setName($request->request->get('name'));
        $reservation->setEmail($request->request->get('email'));
        $reservation->setPhone($request->request->get('phone'));
        $em->flush();
        $this->addFlash('success', 'Réservation modifiée avec succès.');
        return $this->redirectToRoute('admin_users');
    }

    return $this->render('admin/user_edit.html.twig', [
        'reservation' => $reservation,
    ]);
}

#[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
public function userDelete(int $id, ReservationRepository $repo, EntityManagerInterface $em): Response
{
    $reservation = $repo->find($id);
    if ($reservation) {
        $em->remove($reservation);
        $em->flush();
        $this->addFlash('success', 'Réservation supprimée.');
    }
    return $this->redirectToRoute('admin_users');
}
}