<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ReservationController extends AbstractController
{
    #[Route('/events/{id}', name: 'event_show')]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/events/{id}/reserve', name: 'reservation_new', methods: ['GET', 'POST'])]
    public function reserve(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $reservation = new Reservation();
        $errors = [];

        if ($request->isMethod('POST')) {
            $reservation->setEvent($event);
            $reservation->setName($request->request->get('name', ''));
            $reservation->setEmail($request->request->get('email', ''));
            $reservation->setPhone($request->request->get('phone', ''));

            $violations = $validator->validate($reservation);

            if (count($violations) === 0) {
                // Vérifier places disponibles
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

    #[Route('/reservations/{id}/confirm', name: 'reservation_confirm')]
    public function confirm(Reservation $reservation): Response
    {
        return $this->render('reservation/confirm.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
