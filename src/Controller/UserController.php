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
    public function show(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findOneBy(['id' => $id]);
        if (!$user) {
            return new JsonResponse(['error' => ''], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(
            [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername()
            ]
            ,
            JsonResponse::HTTP_OK
        );

    }

    /**
     * @Route("/{id}", name="app_user_edit", methods={"PUT"})
     */
    public function edit(Request $request, int $id, UserRepository $userRepository): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON or empty request body'], JsonResponse::HTTP_BAD_REQUEST);

            }
        } else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $data = $request->request->all();
            print_r(json_encode($request->getContent(), true));
        } else if (strpos($contentType, 'multipart/form-data') !== false) {

            $rawData = $request->getContent();

            preg_match_all('/Content-Disposition: form-data; name="([^"]+)"\r\n\r\n([^--]+)/', $rawData, $matches);

            $form_data = array();
            foreach ($matches[1] as $index => $fieldname) {

                if (strlen($matches[2][$index]) <= 2) {
                    return new JsonResponse(['error' => 'a field is emply'], JsonResponse::HTTP_BAD_REQUEST);
                }
                $data[$fieldname] = trim($matches[2][$index]);
            }
        } else {
            return new JsonResponse(['error' => 'Invalid JSON 2'], JsonResponse::HTTP_BAD_REQUEST);

        }
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => ''], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        $userRepository->add($user, true);

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername()
        ], JsonResponse::HTTP_OK);

    }

}
