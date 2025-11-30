<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EncryptHelper {

    private string $numbers = '0123456789';
    private string $leters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private string $special  = '~!@#$%^&*(){}[],./?';

    public function __construct(private ParameterBagInterface $parameterBag) {}

    /**
     * @param int $length Cantidad de caracteres del hash
     * @return string
     * Metodo para obtener hash aleatorio de letras y numeros
     */
    public function generateRandomString($length = 10, $numeros = true, $letras = true, $caracteres = false): string
    {
        $characters = '';
        if ($numeros) {
            $characters .= $this->numbers;
        }
        if ($letras) {
            $characters .= $this->leters;
        }
        if ($caracteres) {
            $characters .= $this->special;
        }
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param $length int Entero con la cantidad de caracteres del token
     * @return string
     */
    public function getToken($length, $solo_letras = false)
    {
        $token = "";
        if ($solo_letras) {
            $codeAlphabet = "abcdefghijklmnopqrstuvwxyz";
        } else {
            $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
            $codeAlphabet .= "0123456789";
        }
        $max = strlen($codeAlphabet); // edited
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, $max - 1)];
        }
        return $token;
    }

    private function crypto_rand_secure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) return $min; // not so random...
        $log = ceil(log($range, 2));
        $bytes = (int)($log / 8) + 1; // length in bytes
        $bits = (int)$log + 1; // length in bits
        $filter = (int)(1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd > $range);
        return $min + $rnd;
    }

    public function getHash(): string
    {
        $token = "";
        $codeAlphabet = "abcdefghijklmnopqrstuvwxyz";
        $max = strlen($codeAlphabet); // edited
        for ($i = 0; $i < 5; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, $max - 1)];
        }
        $fecha = new \DateTime();
        return $fecha->format('Y') . $fecha->format('m') . $fecha->format('d') . $fecha->format('G') . $fecha->format('i') . $fecha->format('s') . $fecha->format('u') . $token;
    }

    // Validador completo de credenciales
    public function validarCredencialesHeaders($header): bool
    {
        $credencialApi = $this->parameterBag->get('token');
        //dump($credencialApi);die();
        // si el header viene vacio
        if (!$header) {
            return false;
        }
        $client_credentials = base64_decode($header, true);

        // si la decodificación falló
        if ($client_credentials === false) {
            return false;
        }
        // verifica que las credenciales contengan igual longitud
        if (strlen($client_credentials) !== strlen($credencialApi)) {
            return false;
        }
        // si las credenciales no coinciden
        if (!$client_credentials || !hash_equals($credencialApi, $client_credentials)){
            return false;
        }
        return true;
    }

    // Guardar en el registro en el logs
    public function escribirLog($xml, $name): void
    {
        $fecha = new \DateTime();
        $file = fopen($this->parameterBag->get('kernel.project_dir') . '/public/uploads/logs' . $fecha->format('Ymd') . $name . '.txt', "a+");
        fwrite($file, $fecha->format('H:i:s') . PHP_EOL);
        fwrite($file, $xml . PHP_EOL);
        fclose($file);
    }


}
