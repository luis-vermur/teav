<?php

namespace Tests\Feature;

use Tests\TestCase;

class RutaRaizTest extends TestCase
{
    /**
     * La raíz no muestra contenido propio: es la puerta al panel.
     * Sin sesión, /admin manda al login.
     */
    public function test_la_raiz_redirige_al_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_el_panel_sin_sesion_manda_al_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_el_login_responde(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
