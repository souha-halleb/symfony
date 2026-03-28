<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    // ── Gestion des réservations ──

    /**
     * FIX #16 : la route admin_reservations existait dans le contrôleur
     * mais le template admin/reservations.html.twig était absent → 404.
     * On la garde et on s'assure que le template existe (voir templates/admin/reservations.html.twig).
     */
    #[Route('/reservations', name: 'admin_reservations')]
    public function reservations(ReservationRepository $repo): Response
    {
        return $this->render('admin/reservations.html.twig', [
            'reservations' => $repo->findAll(),
        ]);
    }

    // ── Gestion des "utilisateurs" (= réservations dans ce contexte admin) ──

    /**
     * FIX #17 : la route admin_users affichait les réservations sous le nom "users",
     * ce qui est trompeur mais cohérent avec les templates existants. On conserve ce comportement
     * en ajoutant aussi la liste des vrais utilisateurs pour un dashboard complet.
     */
    #[Route('/users', name: 'admin_users')]
    public function users(ReservationRepository $reservationRepo, UserRepository $userRepo): Response
    {
        return $this->render('admin/users.html.twig', [
            'reservations' => $reservationRepo->findAll(),
            // FIX #17 : on passe aussi la liste des User pour un affichage futur
            'users'        => $userRepo->findAll(),
        ]);
    }

    /**
     * FIX #18 : admin_user_edit utilisait un paramètre `int $id`
     * mais le repo->find() retourne null sans lever d'exception.
     * On lève explicitement createNotFoundException() pour un 404 propre.
     * De plus, l'action edit modifiait bien la Reservation (pas un User) → cohérent.
     */
    #[Route('/users/{id}/edit', name: 'admin_user_edit')]
    public function userEdit(int $id, ReservationRepository $repo, Request $request, EntityManagerInterface $em): Response
    {
        $reservation = $repo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        if ($request->isMethod('POST')) {
            $reservation->setName($request->request->get('name', ''));
            $reservation->setEmail($request->request->get('email', ''));
            $reservation->setPhone($request->request->get('phone', ''));
            $em->flush();
            $this->addFlash('success', 'Réservation modifiée avec succès.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_edit.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * FIX #19 : admin_user_delete ne vérifiait pas le token CSRF → vulnérabilité CSRF.
     * On ajoute la vérification du token (cohérent avec le template admin/users.html.twig).
     */
    #[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function userDelete(int $id, ReservationRepository $repo, EntityManagerInterface $em, Request $request): Response
    {
        $reservation = $repo->find($id);
        if ($reservation) {
            // Vérification CSRF (le token est généré dans users.html.twig)
            if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }
        return $this->redirectToRoute('admin_users');
    }
}