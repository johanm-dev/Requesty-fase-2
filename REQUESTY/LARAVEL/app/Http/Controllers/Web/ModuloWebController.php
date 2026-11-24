<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Pqrs;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\Request;

class ModuloWebController extends Controller
{
    public function pedidos(Request $request)
    {
        $user = $request->attributes->get('requesty_user');
        $filterMesero = (strtoupper((string) ($user['rol'] ?? '')) === 'MESERO') ? (int) $user['id'] : null;

        return view('pedidos.index', [
            'pageTitle' => 'Gestion de Pedidos - Requesty',
            'currentPage' => 'pedidos',
            'pedidos' => (new Pedido())->getAll($filterMesero),
            'user' => $user,
        ]);
    }

    public function cocina(Request $request)
    {
        return view('cocina.index', [
            'pageTitle' => 'Cocina - Requesty',
            'currentPage' => 'cocina',
            'pedidos' => (new Pedido())->getAll(),
            'user' => $request->attributes->get('requesty_user'),
        ]);
    }

    public function menu(Request $request)
    {
        $productoModel = new Producto();

        return view('menu.index', [
            'pageTitle' => 'Menu - Requesty',
            'currentPage' => 'menu',
            'productos' => $productoModel->getAll(),
            'categorias' => $productoModel->getCategorias(),
            'user' => $request->attributes->get('requesty_user'),
        ]);
    }

    public function caja(Request $request)
    {
        $user = $request->attributes->get('requesty_user');
        $rol = strtoupper((string) ($user['rol'] ?? ''));
        $facturas = ($rol === 'CLIENTE') ? [] : (new Caja())->getFacturas();

        return view('caja.index', [
            'pageTitle' => 'Caja y Facturacion - Requesty',
            'currentPage' => 'caja',
            'facturas' => $facturas,
            'user' => $user,
        ]);
    }

    public function mesas(Request $request)
    {
        return view('mesas.index', [
            'pageTitle' => 'Gestion de Mesas - Requesty',
            'currentPage' => 'mesas',
            'mesas' => (new Mesa())->getAll(),
            'user' => $request->attributes->get('requesty_user'),
        ]);
    }

    public function reportes(Request $request)
    {
        return view('reportes.index', [
            'pageTitle' => 'Reportes y Administracion - Requesty',
            'currentPage' => 'reportes',
            'usuarios' => (new Usuario())->getAll(),
            'facturas' => (new Caja())->getFacturas(),
            'user' => $request->attributes->get('requesty_user'),
        ]);
    }

    public function cuentas()
    {
        return redirect(url('/reportes') . '#cuentas');
    }

    public function pqrs(Request $request)
    {
        $user = $request->attributes->get('requesty_user');
        $filterCliente = null;
        if (strtoupper((string) ($user['rol'] ?? '')) === 'CLIENTE') {
            $cli = (new Cliente())->findByEmail((string) ($user['correo'] ?? ''));
            $filterCliente = $cli ? (int) $cli['id_cliente'] : -1;
        }

        return view('pqrs.index', [
            'pageTitle' => 'PQRS - Requesty',
            'currentPage' => 'pqrs',
            'pqrs' => (new Pqrs())->getAll($filterCliente),
            'user' => $user,
        ]);
    }
}
