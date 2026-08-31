<?php

require 'vendor/autoload.php';

use App\Kernel;
use App\Entity\User;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$userRepo = $container->get('doctrine')->getRepository(User::class);
$user = $userRepo->loadUserByIdentifier('admin@kitunga.com');

echo "User found: " . ($user ? $user->getEmail() : "NO") . "\n";

if ($user) {
    $hasher = $container->get('security.user_password_hasher');
    $valid = $hasher->isPasswordValid($user, 'adminpassword');
    echo "Password 'adminpassword' valid: " . ($valid ? "YES" : "NO") . "\n";
}
