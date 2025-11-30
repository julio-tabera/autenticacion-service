<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    // Evento para agregar el ID del usuario al token JWT
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();
        // AGREGAR EL ID DEL USUARIO AL TOKEN
        $payload['id'] = $user->getId();
        // Si querés también podés enviar email, nombre, etc.
        // $payload['email'] = $user->getEmail();
        $event->setData($payload);
    }
}
