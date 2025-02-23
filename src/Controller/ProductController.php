<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/product")
 */
class ProductController extends AbstractController
{
    /**
     * @Route("/", name="app_product_index", methods={"GET"})
     */
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();
        $data = array_map(function ($product) {
            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'description' => $product->getDescription(),
                'user' => $product->getUser()->getUsername(),
                'image' => $product->getImage(),
            ];
        }, $products);

        // Retornar la respuesta JSON
        return new JsonResponse($data, JsonResponse::HTTP_OK);

        //return $this->render('product/index.html.twig', [
        //    'products' => $productRepository->findAll(),
        //]);
    }


    /**
     * @Route("/create", name="app_product_create", methods={"POST"})
     */
    public function create(Request $request, ProductRepository $productRepository)
    {
        $contentType = $request->headers->get('Content-Type');

        // Si el contenido es JSON
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON or empty request body'], JsonResponse::HTTP_BAD_REQUEST);
            }
        } else {
            // Si es un formulario, toma los datos de la solicitud
            $data = $request->request->all();
        }

        $product = new Product();

        $form = $this->createForm(ProductType::class, $product);

        //$form->handleRequest($request)->submit($data);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile $File */
            $File = $request->files->get('file');
            if ($File) {
                $filename = uniqid() . '.' . $File->getClientOriginalExtension();

                try {
                    $File->move(
                        $this->getParameter('uploads'),  
                        $filename  
                    );

                    $product->setImage($filename);
                } catch (Exception $e) {
                    return new JsonResponse(['error' => 'Failed to upload image'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            $productRepository->add($product, true);

            return new JsonResponse(['id' => $product->getId()], JsonResponse::HTTP_CREATED);
        }

        return new JsonResponse(['error' => (string) $form->getErrors(true, false), 'message' => 'yes yes yes'], JsonResponse::HTTP_BAD_REQUEST);
    }


    /**
     * @Route("/{id}", name="app_product_show", methods={"GET"})
     */
    public function show(int $id, ProductRepository $productRepository): Response
    {
        /*return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);*/
        $product = $productRepository->findOneBy(['id' => $id]);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'description' => $product->getDescription(),
        ], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/{id}", name="app_product_edit", methods={"PATCH"})
     */
    public function update(int $id, Request $request, ProductRepository $productRepository): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type');

        if (str_contains($contentType, 'application/json')) {

            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON or empty request body'], JsonResponse::HTTP_BAD_REQUEST);
            }
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
            $data = $request->request->all();
        }

        $product = $productRepository->find($id);

        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }

        if (isset($data['price'])) {
            $product->setPrice((float) $data['price']);
        }

        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }

        $productRepository->add($product, true);

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'description' => $product->getDescription(),
        ], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/{id}", name="app_product_delete", methods={"POST"})
     */
    public function delete(int $id, ProductRepository $productRepository): JsonResponse
    {
        // Buscar el producto en la base de datos
        $product = $productRepository->find($id);

        // Si el producto no existe, devolver un error 404
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Eliminar el producto
        $productRepository->remove($product, true);

        // Responder con éxito
        return new JsonResponse(['message' => 'Product deleted successfully'], JsonResponse::HTTP_OK);
    }
}
