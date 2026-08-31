<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    private function getJwtToken($client): string
    {
        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'client@kitunga.com',
                'password' => 'clientpassword',
            ])
        );

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);
        return $data['token'] ?? '';
    }

    public function testGetProducts(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/products');
        
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        
        $products = json_decode($response->getContent(), true);
        $this->assertNotEmpty($products);
        $this->assertArrayHasKey('name', $products[0]);
    }

    public function testCreateOrder(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);
        $this->assertNotEmpty($token, 'Failed to login and get JWT token');

        // Fetch products to get a valid product ID
        $client->request('GET', '/api/products');
        $products = json_decode($client->getResponse()->getContent(), true);
        $productId = $products[0]['id'];

        // Place order
        $client->request(
            'POST',
            '/api/orders',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'deliveryAddress' => '456 Boulevard du 30 Juin, Gombe, Kinshasa',
                'paymentMethod' => 'Orange Money',
                'items' => [
                    [
                        'productId' => $productId,
                        'quantity' => 2,
                    ]
                ]
            ])
        );

        $response = $client->getResponse();
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        
        $orderData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $orderData['status']);
        $this->assertArrayHasKey('order', $orderData);
        $orderId = $orderData['order']['id'];

        // Get history
        $client->request(
            'GET',
            '/api/orders',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $history = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($history);

        // Get detail
        $client->request(
            'GET',
            '/api/orders/' . $orderId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $detail = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($orderId, $detail['id']);
        $this->assertNotEmpty($detail['items']);
    }
}
