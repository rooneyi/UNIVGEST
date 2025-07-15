<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    #[Route('/register', name: 'user_register')]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;
        $success = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            if (!preg_match('/^[^@]+@udbl.ac\.cd$/', $email)) {
                $error = "L'adresse email doit se terminer par @udbl.ac.cd";
            } elseif (strlen($password) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = "Un compte existe déjà avec cette adresse email.";
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles(['ROLE_USER']);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();
                $success = "Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.";
                return $this->redirectToRoute('app_home');
            }
        }
        return $this->render('security/register.html.twig', [
            'error' => $error,
            'success' => $success
        ]);
    }
}

