<?php

namespace App\Http\Controllers;

use App\Models\CargaFiel;
use Illuminate\Http\Request;
use PhpCfdi\Credentials\Credential;
use PDF;

class CargaFielController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $fiel = Credential::openFiles($request->file('cer'), $request->file('key'), $request->contra);

        $sourceString = 'texto a firmar';
        // alias de privateKey/sign/verify
        $signature = $fiel->sign($sourceString);
        echo base64_encode($signature), PHP_EOL;

        // alias de certificado/publicKey/verify
        $verify = $fiel->verify($sourceString, $signature);
        var_dump($verify); // bool(true)

        // objeto certificado
        $certificado = $fiel->certificate();
        echo '<hr>';
        echo '<pre>';
        echo $certificado->rfc(), PHP_EOL; // el RFC del certificado
        echo $certificado->legalName(), PHP_EOL; // el nombre del propietario del certificado
        echo $certificado->branchName(), PHP_EOL; // el nombre de la sucursal (en CSD, en FIEL está vacía)
        echo $certificado->serialNumber()->bytes(), PHP_EOL; // número de serie del certificado
        echo '</pre>';
        echo '<hr>';

        PDF::SetTitle('Hello World');
        PDF::AddPage();
        PDF::Write(0, 'Hello World');
        PDF::AddPage();
        PDF::WriteHTML('Hello Worldasdasdad', true, 0, true, 0);
        PDF::Output(public_path('hello_world.pdf'), 'F');
        PDF::reset();




        dd($certificado, $request->file('pdf'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CargaFiel  $cargaFiel
     * @return \Illuminate\Http\Response
     */
    public function show(CargaFiel $cargaFiel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CargaFiel  $cargaFiel
     * @return \Illuminate\Http\Response
     */
    public function edit(CargaFiel $cargaFiel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CargaFiel  $cargaFiel
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CargaFiel $cargaFiel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CargaFiel  $cargaFiel
     * @return \Illuminate\Http\Response
     */
    public function destroy(CargaFiel $cargaFiel)
    {
        //
    }
}
