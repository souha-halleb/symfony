<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;


/**
 * Contrôleur de gestion des événements (CRUD)
 * Accessible uniquement aux administrateurs
 */
#[Route('/admin/events')]
#[IsGranted('ROLE_ADMIN')]
class EventController extends AbstractController
{
    #[Route('/', name: 'admin_event_index', methods: ['GET'])]
    public function index(EventRepository $eventRepo): Response
    {
        return $this->render('event/index.html.twig', [
            'events' => $eventRepo->findAll(),
        ]);
    }
 
    #[Route('/new', name: 'admin_event_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $event = new Event();

        if ($request->isMethod('POST')) {
            $this->handleEventForm($request, $event, $em, $slugger);
            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('event/form.html.twig', [
            'event' => $event,
            'mode'  => 'new',
        ]);
    }

    #[Route('/{id}/show', name: 'admin_event_show', methods: ['GET'])]
    public function show(Event $event, ReservationRepository $reservationRepo): Response
    {
        return $this->render('event/show_admin.html.twig', [
            'event'        => $event,
            'reservations' => $reservationRepo->findByEvent($event->getId()),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('POST')) {
            $this->handleEventForm($request, $event, $em, $slugger);
            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('event/form.html.twig', [
            'event' => $event,
            'mode'  => 'edit',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    public function delete(Event $event, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            if ($event->getImage()) {
                $imagePath = $this->getParameter('uploads_dir') . '/' . $event->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé avec succès !');
        }

        return $this->redirectToRoute('admin_event_index');
    }

    private function handleEventForm(
        Request $request,
        Event $event,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): void {
        $data = $request->request;

        $event->setTitle($data->get('title', ''));
        $event->setDescription($data->get('description', ''));
        $event->setDate(new \DateTime($data->get('date', 'now')));
        $event->setLocation($data->get('location', ''));
        $event->setSeats((int) $data->get('seats', 0));

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename  = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move($this->getParameter('uploads_dir'), $newFilename);
                $event->setImage($newFilename);
            } catch (FileException $e) {
                
            }
        }

        $em->persist($event);
        $em->flush();
    }
}
