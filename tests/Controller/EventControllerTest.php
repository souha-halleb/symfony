<?php

namespace App\Tests\Controller;

use App\Entity\Admin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHomePageLoads(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav.navbar');
    }

    public function testAdminLoginPageLoads(): void
    {
        $this->client->request('GET', '/admin/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testAdminDashboardRequiresAuth(): void
    {
        $this->client->request('GET', '/admin/');
        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminEventListRequiresAuth(): void
    {
        $this->client->request('GET', '/admin/events/');
        $this->assertResponseRedirects('/admin/login');
    }

    public function testApiRegisterOptionsEndpoint(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register/options',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@issat.tn'])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('challenge', $response);
        $this->assertArrayHasKey('rp', $response);
        $this->assertArrayHasKey('user', $response);
    }

    public function testApiRegisterOptionsRequiresEmail(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register/options',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testApiMeRequiresJwt(): void
    {
        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testApiMeWithValidToken(): void
    {
        // Créer un utilisateur de test et générer un JWT
        $user = new \App\Entity\User('api-test@issat.tn');
        $user->setRoles(['ROLE_USER']);

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token      = $jwtManager->create($user);

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('api-test@issat.tn', $response['email']);
    }

    public function testNonExistentPageReturns404(): void
    {
        $this->client->request('GET', '/page-qui-nexiste-pas');
        $this->assertResponseStatusCodeSame(404);
    }
}
