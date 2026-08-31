<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testRegisterAndLogin(): void
    {
        $client = static::createClient();

        // 1. Test Registration
        $email = 'test_user_' . uniqid() . '@example.com';
        $phone = '+243' . random_int(100000000, 999999999);
        
        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullName' => 'Test User',
                'phone' => $phone,
                'email' => $email,
                'password' => 'securepassword123',
                'address' => '123 Main St, Kinshasa',
            ])
        );

        $response = $client->getResponse();
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertArrayHasKey('referralCode', $responseData['user']);
        $this->assertStringStartsWith('KNB-', $responseData['user']['referralCode']);

        // 2. Test Registration Duplicate check
        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullName' => 'Test User 2',
                'phone' => $phone, // same phone
                'email' => $email, // same email
                'password' => 'anotherpassword',
            ])
        );
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // 3. Test Login and token retrieval
        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'securepassword123',
            ])
        );

        $loginResponse = $client->getResponse();
        $this->assertEquals(200, $loginResponse->getStatusCode(), $loginResponse->getContent());
        
        $loginData = json_decode($loginResponse->getContent(), true);
        $this->assertArrayHasKey('token', $loginData);
    }
}
