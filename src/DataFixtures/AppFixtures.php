<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // Créer l'admin par défaut
        $admin = new Admin();
        $admin->setUsername('admin');
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        // Créer des événements de démo
        $events = [
            [
                'title'       => 'Conférence Tech Sousse 2026',
                'description' => 'Une conférence sur les dernières technologies web, IA et cybersécurité. Rejoignez des experts de l\'industrie pour des présentations et ateliers pratiques.',
                'date'        => new \DateTime('+7 days'),
                'location'    => 'ISSAT Sousse, Amphithéâtre A',
                'seats'       => 200,
            ],
            [
                'title'       => 'Workshop Symfony & API REST',
                'description' => 'Atelier pratique sur le développement d\'APIs RESTful avec Symfony 7, JWT et les meilleures pratiques de sécurité.',
                'date'        => new \DateTime('+14 days'),
                'location'    => 'Salle Informatique 3, ISSAT Sousse',
                'seats'       => 30,
            ],
            [
                'title'       => 'Journée Portes Ouvertes ISSAT',
                'description' => 'Découvrez les formations offertes par l\'ISSAT Sousse. Rencontrez les enseignants et étudiants, visitez les laboratoires.',
                'date'        => new \DateTime('+21 days'),
                'location'    => 'Campus ISSAT Sousse',
                'seats'       => 500,
            ],
            [
                'title'       => 'Hackathon FIA3 2026',
                'description' => '24h pour innover ! Développez une application qui répond à un défi local. Prix à gagner pour les 3 meilleures équipes.',
                'date'        => new \DateTime('+30 days'),
                'location'    => 'Salle des projets, ISSAT Sousse',
                'seats'       => 60,
            ],
            [
                'title'       => 'Séminaire DevOps & Docker',
                'description' => 'Apprenez à containeriser vos applications avec Docker et à automatiser vos déploiements avec CI/CD.',
                'date'        => new \DateTime('+45 days'),
                'location'    => 'Amphithéâtre B, ISSAT Sousse',
                'seats'       => 120,
            ],
        ];

        foreach ($events as $data) {
            $event = new Event();
            $event->setTitle($data['title']);
            $event->setDescription($data['description']);
            $event->setDate($data['date']);
            $event->setLocation($data['location']);
            $event->setSeats($data['seats']);
            $manager->persist($event);
        }

        $manager->flush();

        echo "✅ Fixtures chargées :\n";
        echo "   - Admin : username=admin / password=admin1234\n";
        echo "   - 5 événements créés\n";
    }
}
