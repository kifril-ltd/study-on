<?php

namespace App\Controller;

use App\Dto\Request\UserRegisterDto;
use App\Dto\Response\Transformer\UserAuthDtoTransformer;
use App\Exception\BillingException;
use App\Exception\BillingUnavailableException;
use App\Form\RegisterType;
use App\Security\BillingAuthenticator;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_course_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
    }

    #[Route(path: '/register', name: 'app_register')]
    public function register(
        Request                    $request,
        UserAuthenticatorInterface $authenticator,
        BillingAuthenticator       $formAuthenticator,
        BillingClient              $billingClient
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $registerRequest = new UserRegisterDto();
        $form = $this->createForm(RegisterType::class, $registerRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $billingClient->register($registerRequest);
            } catch (BillingException $e) {
                return $this->render('security/registration.html.twig', [
                    'form' => $form->createView(),
                    'errors' => json_decode($e->getMessage(), true),
                ]);
            } catch (BillingUnavailableException $e) {
                return $this->render('security/registration.html.twig', [
                    'form' => $form->createView(),
                    'errors' => ['billing' => [$e->getMessage()]],
                ]);
            }

            return $authenticator->authenticateUser(
                $user,
                $formAuthenticator,
                $request
            );
        }

        return $this->render('security/registration.html.twig', [
            'form' => $form->createView(),
            'errors' => ''
        ]);
    }
}
