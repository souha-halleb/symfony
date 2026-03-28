<?php

namespace App\Controller;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
/**
 * controleur de gestion des reservations d’evenements
 * permet aux utilisateurs de consulter un evenement et reserver une place
 */

class ReservationController extends AbstractController
{/**
     * affiche les details dun evenement
     * Route : GET /events/{id}
     */
    #[Route('/events/{id}', name: 'event_show')]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }
/**
     * creation d’une réservation pour un événement
     * route : GET + POST /events/{id}/reserve
     */
    #[Route('/events/{id}/reserve', name: 'reservation_new', methods: ['GET', 'POST'])]
    public function reserve(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        // Rediriger vers login si non connecte
        if (!$this->isGranted('ROLE_USER')) {
            $this->addFlash('warning', 'Vous devez être connecté pour réserver un événement.');
            return $this->redirectToRoute('user_login');
        }

        $reservation = new Reservation();
        $errors = [];

        if ($request->isMethod('POST')) {
            $reservation->setEvent($event);
            $reservation->setName($request->request->get('name', ''));
            $reservation->setEmail($request->request->get('email', ''));
            $reservation->setPhone($request->request->get('phone', ''));

            $violations = $validator->validate($reservation);

            if (count($violations) === 0) {
                if ($event->getAvailableSeats() <= 0) {
                    $errors[] = 'Désolé, il n\'y a plus de places disponibles pour cet événement.';
                } else {
                    $em->persist($reservation);
                    $em->flush();

                    return $this->redirectToRoute('reservation_confirm', [
                        'id' => $reservation->getId(),
                    ]);
                }
            } else {
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
            }
        }

        return $this->render('reservation/new.html.twig', [
            'event'       => $event,
            'reservation' => $reservation,
            'errors'      => $errors,
        ]);
    }
 /**
     * Page de confirmation de reservation
     * Route : GET /reservations/{id}/confirm
     */
    #[Route('/reservations/{id}/confirm', name: 'reservation_confirm')]
    public function confirm(Reservation $reservation): Response
    {
        return $this->render('reservation/confirm.html.twig', [
            'reservation' => $reservation,
        ]);
    }

#[Route('/mes-reservations', name: 'user_reservations')]
#[IsGranted('ROLE_USER')]
public function mesReservations(EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    $reservations = $em->getRepository(Reservation::class)->findBy([
        'email' => $user->getUserIdentifier()
    ]);

    return $this->render('reservation/mes_reservations.html.twig', [
        'reservations' => $reservations,
    ]);
}
#[Route('/reservations/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function cancel(int $id, EntityManagerInterface $em): Response
{
    $reservation = $em->getRepository(Reservation::class)->find($id);
    if ($reservation) {
        $em->remove($reservation);
        $em->flush();
        $this->addFlash('success', 'Réservation annulée.');
    }
    return $this->redirectToRoute('user_reservations');
}
}