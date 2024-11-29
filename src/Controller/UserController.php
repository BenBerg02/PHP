<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/", name="app_user_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        if (empty($users)) {
            return new JsonResponse(['message' => 'No users found'], JsonResponse::HTTP_OK);
        }

        // Transforma los usuarios a un formato adecuado para la respuesta
        $data = array_map(function (User $user) {
            return [
                "id" => $user->getId(),
                "name" => $user->getUsername(),
                "email" => $user->getEmail(),
            ];
        }, $users);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
    
    /**
     * @Route("/", name="app_user_new", methods={"POST"})
     */
    public function create(Request $request, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON or empty request body'], JsonResponse::HTTP_BAD_REQUEST);
            }
        } else {
            $data = $request->request->all();
        }

        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();

            // Si la contraseña es válida, la encriptamos
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Guardamos el nuevo usuario
            $userRepository->add($user, true);

            return new JsonResponse(['message' => 'User registered successfully'], JsonResponse::HTTP_CREATED);
        }

        // Si el formulario no es válido, devolvemos el error
        return new JsonResponse(['error' => 'Invalid form data'], JsonResponse::HTTP_BAD_REQUEST);
    }


    /**
     * @Route("/{id}", name="app_user_show", methods={"GET"})
     */
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_user_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, User $user, UserRepository $userRepository): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userRepository->add($user, true);

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_user_delete", methods={"POST"})
     */
    public function delete(Request $request, User $user, UserRepository $userRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $userRepository->remove($user, true);
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
