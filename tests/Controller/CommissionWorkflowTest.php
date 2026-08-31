<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommissionWorkflowTest extends WebTestCase
{
    private function getLoginToken($client, string $email, string $password): string
    {
        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );
        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['token'] ?? '';
    }

    public function testCompleteReferralCommissionWorkflow(): void
    {
        $client = static::createClient();

        $suffix = uniqid();
        $emailA = 'referrer_a_' . $suffix . '@example.com';
        $phoneA = '+2439' . random_int(10000000, 99999999);

        $emailB = 'referrer_b_' . $suffix . '@example.com';
        $phoneB = '+2439' . random_int(10000000, 99999999);

        $emailC = 'buyer_c_' . $suffix . '@example.com';
        $phoneC = '+2439' . random_int(10000000, 99999999);

        // 1. Register User A (Referrer)
        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullName' => 'Referrer A',
                'phone' => $phoneA,
                'email' => $emailA,
                'password' => 'password123',
                'address' => 'Gombe, Kinshasa',
            ])
        );
        $this->assertEquals(201, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $userA = json_decode($client->getResponse()->getContent(), true)['user'];
        $codeA = $userA['referralCode'];

        // 2. Register User B (Referrer B, parrainé par A)
        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullName' => 'Referrer B',
                'phone' => $phoneB,
                'email' => $emailB,
                'password' => 'password123',
                'address' => 'Gombe, Kinshasa',
                'referrerCode' => $codeA
            ])
        );
        $this->assertEquals(201, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $userB = json_decode($client->getResponse()->getContent(), true)['user'];
        $codeB = $userB['referralCode'];

        // 3. Register User C (Buyer C, parrainé par B)
        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullName' => 'Buyer C',
                'phone' => $phoneC,
                'email' => $emailC,
                'password' => 'password123',
                'address' => 'Gombe, Kinshasa',
                'referrerCode' => $codeB
            ])
        );
        $this->assertEquals(201, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $userC = json_decode($client->getResponse()->getContent(), true)['user'];
        $userIdC = $userC['id'];

        // 4. Logins
        $adminToken = $this->getLoginToken($client, 'admin@kitunga.com', 'adminpassword');
        $tokenC = $this->getLoginToken($client, $emailC, 'password123');

        // 5. Fetch a product to order (Riz de Bumba is price 25000 FC)
        $client->request('GET', '/api/products');
        $products = json_decode($client->getResponse()->getContent(), true);
        $rizId = null;
        foreach ($products as $p) {
            if ($p['name'] === 'Riz de Bumba') {
                $rizId = $p['id'];
                break;
            }
        }
        $this->assertNotNull($rizId, 'Riz de Bumba not found in products');

        // 6. User C places order for 4 Riz de Bumba = 100 000 FC
        $client->request(
            'POST',
            '/api/orders',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenC,
            ],
            json_encode([
                'deliveryAddress' => '789 Route de Limete, Kinshasa',
                'paymentMethod' => 'M-Pesa',
                'items' => [
                    [
                        'productId' => $rizId,
                        'quantity' => 4
                    ]
                ]
            ])
        );
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $order = json_decode($client->getResponse()->getContent(), true)['order'];
        $orderId = $order['id'];
        $this->assertEquals('100000.00', $order['subtotal']);

        // 7. Admin updates order status to 'delivered'
        $client->request(
            'PUT',
            '/api/admin/orders/' . $orderId . '/status',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            ],
            json_encode([
                'status' => 'delivered'
            ])
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // 8. User C confirms delivery reception
        $client->request(
            'POST',
            '/api/orders/' . $orderId . '/confirm',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenC,
            ]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        // 9. Login User B (L1) and check wallet
        $tokenB = $this->getLoginToken($client, $emailB, 'password123');
        $client->request(
            'GET',
            '/api/wallet',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $walletB = json_decode($client->getResponse()->getContent(), true);
        // B should have 5% of 100 000 FC = 5000 FC
        $this->assertEquals('5000.00', $walletB['availableBalance']);
        $this->assertEquals('5000.00', $walletB['totalGenerated']);
        $this->assertEquals('0.00', $walletB['totalPaid']);

        // 10. Login User A (L2) and check wallet
        $tokenA = $this->getLoginToken($client, $emailA, 'password123');
        $client->request(
            'GET',
            '/api/wallet',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $walletA = json_decode($client->getResponse()->getContent(), true);
        // A should have 2% of 100 000 FC = 2000 FC
        $this->assertEquals('2000.00', $walletA['availableBalance']);
        $this->assertEquals('2000.00', $walletA['totalGenerated']);

        // 11. Admin registers a payment of 3000 FC to User B
        $client->request(
            'POST',
            '/api/admin/payments',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            ],
            json_encode([
                'beneficiaryId' => $userB['id'],
                'amount' => '3000.00',
                'paymentMethod' => 'M-Pesa',
                'transactionReference' => 'TXN-MPESA-9999'
            ])
        );
        $this->assertEquals(201, $client->getResponse()->getStatusCode());

        // 12. Re-login B and check wallet update
        $client->request(
            'GET',
            '/api/wallet',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]
        );
        $walletB_updated = json_decode($client->getResponse()->getContent(), true);
        // B should have 5000 - 3000 = 2000 FC available, and 3000 FC paid
        $this->assertEquals('2000.00', $walletB_updated['availableBalance']);
        $this->assertEquals('5000.00', $walletB_updated['totalGenerated']);
        $this->assertEquals('3000.00', $walletB_updated['totalPaid']);

        // 13. Check Admin Dashboard API
        $client->request(
            'GET',
            '/api/admin/dashboard',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $dashboard = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(3, $dashboard['totalClients']);
        $this->assertGreaterThanOrEqual(1, $dashboard['totalOrders']);
        $this->assertGreaterThanOrEqual(105000.00, floatval($dashboard['totalSales']));
        $this->assertGreaterThanOrEqual(7000.00, floatval($dashboard['totalCommissionsGenerated']));
        $this->assertGreaterThanOrEqual(3000.00, floatval($dashboard['totalCommissionsPaid']));

        // 14. Check Admin Clients List and Network API
        $client->request(
            'GET',
            '/api/admin/clients',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $clientsList = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($clientsList);

        $client->request(
            'GET',
            '/api/admin/clients/' . $userA['id'] . '/network',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $networkTree = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($userA['fullName'], $networkTree['client']['fullName']);
        // Level 1 should have Referrer B
        $this->assertCount(1, $networkTree['level1Referrals']);
        $this->assertEquals($userB['fullName'], $networkTree['level1Referrals'][0]['fullName']);
        // Under Referrer B there should be Buyer C
        $this->assertCount(1, $networkTree['level1Referrals'][0]['subNetwork']);
        $this->assertEquals($userC['fullName'], $networkTree['level1Referrals'][0]['subNetwork'][0]['fullName']);

        // 15. Check Notifications APIs for User B
        $client->request(
            'GET',
            '/api/notifications',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $notifs = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($notifs);
        $notifId = $notifs[0]['id'];

        // Mark read
        $client->request(
            'PUT',
            '/api/notifications/' . $notifId . '/read',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // 16. Admin Product CRUD
        $client->request(
            'POST',
            '/api/admin/products',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            ],
            json_encode([
                'name' => 'Semoule de manioc (Foufou)',
                'description' => 'Fine semoule de manioc de Kongolo.',
                'price' => '11500.00',
                'category' => 'Féculents',
                'imageUrl' => 'foufou.jpg',
                'stock' => 100
            ])
        );
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $newProd = json_decode($client->getResponse()->getContent(), true)['product'];
        $newProdId = $newProd['id'];

        // Update product
        $client->request(
            'PUT',
            '/api/admin/products/' . $newProdId,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            ],
            json_encode([
                'price' => '12000.00'
            ])
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Delete product
        $client->request(
            'DELETE',
            '/api/admin/products/' . $newProdId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
