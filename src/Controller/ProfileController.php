<?php

namespace App\Controller;

use App\Dto\Response\CurrentUserDto;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(BillingClient $billingClient): Response
    {
        /** @var CurrentUserDto $currentUser */
        $currentUser = $billingClient->getUser($this->getUser()->getApiToken());

        return $this->render('profile/index.html.twig', [
            'email' => $currentUser->username,
            'role' => in_array('ROLE_SUPER_ADMIN', $currentUser->roles) ? 'Администратор' : 'Пользователь',
            'balance' => $currentUser->balance
        ]);
    }

    #[Route('/history', name: 'app_history')]
    public function history(BillingClient $billingClient, CourseRepository $courseRepository): Response
    {
        $transactions = $billingClient->getTransactions([], $this->getUser()->getApiToken());
        uasort($transactions, function ($a, $b) {
            return $a['created_at'] <=> $b['created_at'];
        });

        return $this->render('profile/history.html.twig', [
            'transactions' => $transactions,
        ]);
    }
}
