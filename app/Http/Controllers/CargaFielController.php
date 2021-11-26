<?php

namespace App\Http\Controllers;

use PDF;
use Throwable;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\CargaFiel;
use Illuminate\Http\Request;
use PhpCfdi\Credentials\PublicKey;
use PhpCfdi\Credentials\Credential;
use Illuminate\Support\Facades\Storage;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

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
        if ($request->cer && $request->key && $request->contra && $request->pdf) {
            $cerFile = 'file://' . $request->cer;
            $pemKeyFile = 'file://' . $request->key;
            $passPhrase = $request->contra;
            $contractName = "SilentPrueba";
            $name = $request->file('pdf')->getClientOriginalName();
            $path = $request->file('pdf')->move('FIEL', $name);

            try {
                $fiel = Credential::openFiles($cerFile, $pemKeyFile, $passPhrase);
            } catch (Throwable $exception) {
                notify()->error('La contraseña no corresponde a este certificado');
                return redirect()->back();
            }
            $certificado = $fiel->certificate();

            if ($certificado->satType()->isCsd()) {
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

                echo '<hr>';
                echo '<pre>';
                echo $certificado->name();
                echo $certificado->rfc(), PHP_EOL; // el RFC del certificado
                echo $certificado->legalName(), PHP_EOL; // el nombre del propietario del certificado
                echo $certificado->branchName(), PHP_EOL; // el nombre de la sucursal (en CSD, en FIEL está vacía)
                echo $certificado->serialNumber()->bytes(), PHP_EOL; // número de serie del certificado
                echo '</pre>';
                echo '<hr>';

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

                \QrCode::size(500)
                    ->format('png')
                    ->generate(base64_encode($signature), public_path(time() . $certificado->rfc() . '.png'));

                // Get the image and convert into string
                $img = file_get_contents(
                    public_path(time() . $certificado->rfc() . '.png')
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
                file_put_contents('sellado.pdf', $dompdf->output());
                //$dompdf->stream('FicheroEjemplo.pdf');
                $oMerger = PDFMerger::init();
                $oMerger->addPDF('89191.pdf', 'all');
                $oMerger->addPDF('sellado.pdf', 'all');
                $oMerger->merge();
                $oMerger->save('merged_result.pdf');
                notify()->success('Documento sellado correctamente');
                return redirect()->back();
            } else {
                notify()->error('El certificado no es correcto');
                return redirect()->back();
            }
        } else {
            notify()->error('No se han cargado los documentos necesarios');
            return redirect()->back();
        }
    }

    public function strToHex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }
}
