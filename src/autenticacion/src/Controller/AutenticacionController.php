<?php

namespace App\Controller;

use App\Entity\AEmailEvento;
use App\Entity\Usuario;
use App\Enum\EmailEventosEnum;
use App\Helper\EncryptHelper;
use App\Repository\AEmailRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AutenticacionController extends AbstractController
{

    // <editor-fold defaultstate="collapsed" desc="API REGISTRAR USUARIO">
    #[Route('/auth-registro', name: 'auth_registro', methods: ['POST'])]
    public function authRegistro(Request                     $request, EntityManagerInterface $em,
                                 UserPasswordHasherInterface $hasher, UsuarioRepository $usuarioRepository): JsonResponse
    {
        if ($request->getMethod() == Request::METHOD_POST) {
            try {
                $json = $request->getContent();
                $this->escribirLog($json);
                $data = json_decode($request->getContent(), true);
                if (!isset($data['username'], $data['password'])) {
                    return $this->json(['estado' => 'error', 'mensaje' => 'usuario y contraseña son requeridos'], Response::HTTP_BAD_REQUEST);
                }
                $userName = $usuarioRepository->findOneBy(['username' => $data['username']]);
                if ($userName) {
                    return $this->json([
                        'estado' => 'ERROR',
                        'mensaje' => "Usuario {$data['username']} está siendo usado"],
                        Response::HTTP_BAD_REQUEST);
                }
                $encrypt = new EncryptHelper();
                $user = new Usuario();
                $user->setUsername($data['username']);
                $user->setHash($encrypt->getHash());
                $user->setPassword(
                    $hasher->hashPassword($user, $data['password'])
                );
                if (isset($data['roles']) && is_array($data['roles'])) {
                    $user->setRoles($data['roles']);
                }
                $em->persist($user);
                $em->flush();
                return $this->json([
                    'estado' => 'Ok',
                    'mensaje' => "Usuario {$user->getUsername()} ha sido creado correctamente"],
                    Response::HTTP_OK);

            } catch (\Exception $ex) {
                $this->escribirLog('ERROR ' . $ex->getMessage());
                return $this->json(['estado' => 'ERROR', 'mensaje' => 'Ocurrió un problema inesperado'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return $this->json(['estado' => 'ERROR', 'mensaje' => 'No se encontraron respuestas'], Response::HTTP_NOT_ACCEPTABLE);
    }

    //</editor-fold>

    private function escribirLog($xml): void
    {
        $fecha = new \DateTime();
        $file = fopen($this->getParameter('kernel.project_dir') . '/public/uploads/' . $fecha->format('Ymd') . 'autenticacion.txt', "a+");
        fwrite($file, $fecha->format('H:i:s') . PHP_EOL);
        fwrite($file, $xml . PHP_EOL);
        fclose($file);
    }
}
