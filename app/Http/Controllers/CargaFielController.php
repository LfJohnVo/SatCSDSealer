<?php

namespace App\Http\Controllers;

use PDF;
use File;
use Response;
use Throwable;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\CargaFiel;
use Illuminate\Http\Request;
use PhpCfdi\Credentials\PublicKey;
use PhpCfdi\Credentials\Credential;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

class CargaFielController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->cer && $request->key && $request->contra && $request->pdf) {
            $cerFile = 'file://' . $request->cer;
            $pemKeyFile = 'file://' . $request->key;
            $passPhrase = $request->contra;
            $contractName = "SilentPrueba";
            $input_file = $request->file('pdf')->getClientOriginalName();
            $name = pathinfo($input_file, PATHINFO_FILENAME);
            $path = $request->file('pdf')->move('FIEL', $name . '.pdf');

            try {
                $fiel = Credential::openFiles($cerFile, $pemKeyFile, $passPhrase);
            } catch (Throwable $exception) {
                notify()->error('La contraseña no corresponde a este certificado');
                return redirect()->back();
            }
            $certificado = $fiel->certificate();

            if ($certificado->satType()->isCsd()) {
                // $sourceString = $contractName;
                // // alias de privateKey/sign/verify
                // $signature = $fiel->sign($sourceString);
                // //echo base64_encode($signature), PHP_EOL;
                // // alias de certificado/publicKey/verify
                // $verify = $fiel->verify($sourceString, $signature);
                // //var_dump($verify); // bool(true)

                // // objeto publicKey
                // $publicKey = explode('/', $certificado->name());
                // $country = substr($publicKey[1], -2, 2);
                // $state = substr($publicKey[2], 3);
                // $localityName = substr($publicKey[3], 2);
                // $organizacion = substr($publicKey[4], 2);
                // $OrgUnitName = substr($publicKey[5], 3);
                // $commonName = substr($publicKey[6], 3);
                // $email = substr($publicKey[7], 13);

                // // // set additional information in the signature
                // // $info = array(
                // //     'Name' => $certificado->legalName(),
                // //     'Location' => $state,
                // //     'Rfc' => $certificado->rfc(),
                // //     'ContactInfo' => $email,
                // //     'Curp' => $curp,
                // //     'SerialNumber' => $certificado->serialNumber()->bytes(),
                // // );
                // // set additional information in the signature
                // $info = array(
                //     'Name' => $commonName,
                //     'Estado' => $state,
                //     'País' => $country,
                //     'Location' => $localityName,
                //     'ContactInfo' => $email,
                //     'Organizacion' => $organizacion,
                //     'UnidadOrganizacion' => $OrgUnitName,
                // );
                // dd($info);
                notify()->success('El certificado es un CSD normal');
                return redirect()->back();
            } elseif ($certificado->satType()->isFiel()) {
                $sourceString = $contractName;
                // alias de privateKey/sign/verify
                $signature = $fiel->sign($sourceString);
                //echo base64_encode($signature), PHP_EOL;
                // alias de certificado/publicKey/verify
                $verify = $fiel->verify($sourceString, $signature);
                var_dump($verify); // bool(true)

                // 'verificacion' => $verify,
                // 'FIRMA ELECTRÓNICA' => base64_encode($signature),
                // 'rfc' => $certificado->rfc(),
                // 'legalName' => $request->name,
                // 'branchName' => $certificado->branchName(),
                // 'NO. DE SERIE SCD' => $str

                try {
                    $data = $certificado->serialNumber()->bytes();
                    $str = $this->strToHex($data);
                } catch (Throwable $exception) {
                    $str = 'N/A';
                }

                // objeto publicKey
                $publicKey = explode('/', $certificado->name());
                $state = substr($publicKey[4], -2, 2);
                $email = substr($publicKey[5], 13);
                $curp = substr($publicKey[7], 13);

                // set additional information in the signature
                $info = array(
                    'Name' => $certificado->legalName(),
                    'Location' => $state,
                    'Rfc' => $certificado->rfc(),
                    'ContactInfo' => $email,
                    'Curp' => $curp,
                    'SerialNumber' => $certificado->serialNumber()->bytes(),
                );

                $QrName = time() . $certificado->rfc();

                \QrCode::size(500)
                    ->format('png')
                    ->generate(base64_encode($signature), public_path($QrName . '.png'));

                $img = file_get_contents(
                    public_path($QrName . '.png')
                );

                $base64 = base64_encode($img);
                $fTimbrado = $mytime = Carbon::now();

                $html = '';
                $html .= '
                    <table>
                            <tbody>
                                <tr>
                                    <td><strong>Firmante: </strong>' . $certificado->legalName() . '</td>
                                    <td><img src="data:image/png;base64, ' . $base64 . '" style="width: 140px; padding-left: 30px; padding-top: 30px;"></td>
                                </tr>
                                <tr>
                                    <td><strong>Folio: </strong>8857</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Nombre legal: </strong>' . $certificado->legalName() . '</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Correo: </strong>' . $email . '</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>RFC: </strong>' . $certificado->rfc() . '</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>No. de Serie CSD: </strong>' . $str . '</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Fecha y hora de firma del documento: </strong>' . $mytime->toDateTimeString() . '</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Firma Digital: </strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        <table>
                            <tbody>
                                <tr>
                                    <td><textarea class="form-control" style=" height: 120px;">' . wordwrap(base64_encode($signature), 75, "<br>", TRUE) . '</textarea></td>
                                </tr>
                            </tbody>
                        </table>
                    ';
                // echo $html;
                // dd($html);
                $options = new Options();
                $options->setIsRemoteEnabled(true);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
                file_put_contents($certificado->rfc() . '-sellado.pdf', $dompdf->output());
                $oMerger = PDFMerger::init();
                $oMerger->addPDF('FIEL/' . $name . '.pdf', 'all');
                $oMerger->addPDF($certificado->rfc() . '-sellado.pdf', 'all');
                $oMerger->merge();
                $oMerger->save('Sellados/' . $name . '-sellado.pdf');
                unlink($certificado->rfc() . '-sellado.pdf');
                unlink('FIEL/' . $name . '.pdf');
                unlink($QrName . '.png');
                notify()->success('Documento sellado correctamente');
                return Redirect::to('/');
            } else {
                notify()->error('El certificado no es correcto');
                return redirect()->back();
            }
        } else {
            notify()->error('No se han cargado los documentos necesarios');
            return redirect()->back();
        }
    }

    public function download(Request $request)
    {
        //Define header information
        $filepath = public_path('Sellados/' . $request->file);
        return Response::download($filepath);
    }

    public function strToHex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    public function hexToStr($hex)
    {
        $string = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }
}
