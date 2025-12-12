<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Helper\EncryptHelper;
use App\Repository\UsuarioRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
class AutenticacionController extends AbstractController
{
    //base64 eENyT0xscHU4RkFJZkdjbTBQUjQ0NlJJMFBmUEFTUHg=

    // <editor-fold defaultstate="collapsed" desc="API REGISTRAR USUARIO">
    #[Route('/registro', name: 'auth_registro', methods: ['POST'])]
    public function authRegistro(Request                     $request,
                                 UserPasswordHasherInterface $hasher,
                                 UsuarioRepository           $usuarioRepository,
                                 EncryptHelper               $encryptHelper): JsonResponse
    {
        if ($request->getMethod() == Request::METHOD_POST) {
            try {

                $json = $request->getContent();
                $data = json_decode($json, true);
                $encryptHelper->escribirLog($json, 'log_autenticacion');

                // verifica que la codificacion del json haya sido correcta
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->json([
                        'estado' => 'ERROR',
                        'mensaje' => 'Json inválido'
                    ], Response::HTTP_BAD_REQUEST);
                }


                // se verifica si la variable username y password estan definidos en el array data
                if (!isset($data['username'], $data['password'])) {
                    return $this->json(['estado' => 'error', 'mensaje' => 'usuario y contraseña son requeridos'], Response::HTTP_BAD_REQUEST);
                }

                // se verifica si la variable username y password no esten vacias
                if (empty(trim($data['username'])) || empty(trim($data['password']))) {
                    return $this->json([
                        'estado' => 'ERROR',
                        'mensaje' => 'Usuario y contraseña son requeridos'
                    ], Response::HTTP_BAD_REQUEST);
                }


                $userName = $usuarioRepository->findOneBy(['username' => $data['username']]);
                if ($userName) {
                    return $this->json([
                        'estado' => 'ERROR',
                        'mensaje' => "El usuario ya existe"],
                        Response::HTTP_BAD_REQUEST);
                }
                $user = new Usuario();
                $user->setUsername($data['username']);
                $user->setHash($encryptHelper->getHash());
                $user->setPassword(
                    $hasher->hashPassword($user, $data['password'])
                );
                if (isset($data['roles']) && is_array($data['roles'])) {
                    $user->setRoles($data['roles']);
                }
                $usuarioRepository->save($user, true);
                return $this->json([
                    'estado' => 'OK',
                    'mensaje' => "Usuario creado correctamente"],
                    Response::HTTP_OK);

            } catch (\Exception $ex) {
                $encryptHelper->escribirLog('ERROR ' . $ex->getMessage(), 'log_autenticacion');
                return $this->json(['estado' => 'ERROR', 'mensaje' => 'Error interno'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return $this->json(['estado' => 'ERROR', 'mensaje' => 'No se encontraron respuestas'], Response::HTTP_NOT_ACCEPTABLE);
    }

    //</editor-fold>

    // <editor-fold defaultstate="collapsed" desc="API OBTENER USUARIO LOGUEADO">
    #[Route('/usuario-logueado', name: 'auth_usuario_logueado', methods: ['GET'])]
    public function authUsuarioLogueado(Request $request, EncryptHelper $encryptHelper): JsonResponse
    {
        $header = $request->headers->get('client-tokenid');
        if (!$encryptHelper->validarCredencialesHeaders($header)){
            return $this->json([
                'estado' => 'ERROR',
                'mensaje' => 'Error en la conexion'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'estado' => 'ERROR',
                'mensaje' => 'No autenticado'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isEnabled()) {
            return $this->json([
                'estado' => 'ERROR',
                'mensaje' => 'Usuario deshabilitado'
            ], Response::HTTP_FORBIDDEN);
        }

        // se verifica que el rol no sea ROLE_SUPER_ADMIN, ROLE_ADMIN para no exponerlo
        $roles = array_filter($user->getRoles(), function ($role) {
            return !in_array($role, ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
        });

        $response = $this->json([
            'estado' => 'OK',
            'usuario' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => array_values($roles),
                'enabled' => $user->isEnabled()
            ]
        ]);
        // Para evitar que proxies o navegadores almacenen la información del usuario
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    //</editor-fold>

    // <editor-fold defaultstate="collapsed" desc="API INTROSPECCION DE TOKEN">
    #[Route('/token-introspeccion', name: 'auth_introspeccion', methods: ['POST'])]
    public function authIntrospeccionToken(Request $request,
                                           JWTTokenManagerInterface $jwtManager,
                                           EncryptHelper $encryptHelper): JsonResponse
    {
        $header = $request->headers->get('client-tokenid');
        if (!$encryptHelper->validarCredencialesHeaders($header)){
            return $this->json([
                'estado' => 'ERROR',
                'mensaje' => 'Error en la conexion'
            ], Response::HTTP_UNAUTHORIZED);
        }
        $json = $request->getContent();
        $data = json_decode($json, true);

        // JSON inválido
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'estado' => false,
                'mensaje' => 'JSON inválido'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validar token presente
        if (empty($data['token']) || !is_string($data['token']) || trim($data['token']) === '') {
            return $this->json([
                'estado' => false,
                'mensaje' => 'Token requerido'
            ], Response::HTTP_BAD_REQUEST);
        }

        $token = trim($data['token']);

        try {
            // parse() valida y decodifica el JWT (firma, exp, etc.)
            $payload = $jwtManager->parse($token);

            // Token inválido o manipulado
            if (!$payload) {
                return $this->json(['estado' => false], Response::HTTP_OK);
            }

            // Verificar expiración manual (por seguridad extra)
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return $this->json(['estado' => false], Response::HTTP_OK);
            }

            // Token válido
            return $this->json([
                'active' => true,
                'username' => $payload['username'] ?? null,
                'roles' => $payload['roles'] ?? [],
                'exp' => $payload['exp'] ?? null
            ], Response::HTTP_OK);

        } catch (\Exception $e) {

            // Registrar intento fallido
            $log = json_encode([
                'ip' => $request->getClientIp(),
                'token' => substr($token, 0, 8) . '...len:' . strlen($token),
                'error' => $e->getMessage(),
            ]);

            $encryptHelper->escribirLog($log, 'log_validacion_token');
            return $this->json(['estado' => false], Response::HTTP_OK);
        }
    }

    //</editor-fold>

    // <editor-fold defaultstate="collapsed" desc="API OBTENER USUARIO POR USERNAME O ID">
    #[Route('/usuario-find', name: 'auth_usuario_find', methods: ['POST'])]
    public function authUsuarioFind( Request $request,
                                     UsuarioRepository $usuarioRepository,
                                     EncryptHelper $encryptHelper): JsonResponse
    {
        try {
            $json = $request->getContent();
            $data = json_decode($json, true);
            $usuario =null;

            // verifica que la codificacion del json haya sido correcta
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'estado' => 'ERROR',
                    'mensaje' => 'Json inválido'
                ], Response::HTTP_BAD_REQUEST);
            }
            // se verifica si la clave username o id esta definida en el array data y no sean vacias
            if (!is_null($data)){

                if (isset($data['username']) && !empty(trim($data['username']))) {
                    $usuario = $usuarioRepository->findOneBy(['username' => $data['username']]);
                }elseif (isset($data['id']) && is_numeric($data['id'])){
                    $usuario = $usuarioRepository->find($data['id']);
                }else{
                    return $this->json(['estado' => 'ERROR', 'mensaje' => 'nombre de usuario o id requerido'], Response::HTTP_BAD_REQUEST);
                }
            }

            // usuario no encontrado
            if (!$usuario) {
                return $this->json([
                    'estado'  => 'ERROR',
                    'mensaje' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$usuario->isEnabled()) {
                return $this->json([
                    'estado' => 'ERROR',
                    'mensaje' => 'Usuario deshabilitado'
                ], Response::HTTP_FORBIDDEN);
            }

            // se verifica que el rol no sea ROLE_SUPER_ADMIN, ROLE_ADMIN para no exponerlo
            $roles = array_filter($usuario->getRoles(), function ($role) {
                return !in_array($role, ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
            });

            $response = $this->json([
                'estado' => 'OK',
                'usuario' => [
                    'id' => $usuario->getId(),
                    'username' => $usuario->getUsername(),
                    'roles' => array_values($roles),
                    'enabled' => $usuario->isEnabled()
                ]
            ]);
            // Para evitar que proxies o navegadores almacenen la información del usuario
            $response->headers->set('Cache-Control', 'no-store');
            return $response;
        } catch (\Exception $ex) {
            $encryptHelper->escribirLog('ERROR ' . $ex->getMessage(), 'log_find_usuario');
            return $this->json(['estado' => 'ERROR', 'mensaje' => 'Error interno'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    //</editor-fold>



}
