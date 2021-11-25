<?php

namespace App\Http\Controllers;

use PDF;
use Dompdf\Dompdf;
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
        if ($request->cer && $request->key && $request->contra) {
            $cerFile = 'file://' . $request->cer;
            $pemKeyFile = 'file://' . $request->key;
            $passPhrase = $request->contra;
            $contractName = "SilentPrueba";

            try {
                $fiel = Credential::openFiles($cerFile, $pemKeyFile, $passPhrase);
            } catch (Exception $exception) {
                return redirect()->back()->with('warning', 'Parece que el password no es correcto!');
            }
            $certificado = $fiel->certificate();

            if ($certificado->satType()->isCsd()) {
                echo 'CSD', PHP_EOL;
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
                } catch (Exception $exception) {
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

                $html = '';
                $html .= '
                    <table>
                            <tbody>
                                <tr>
                                    <td><strong>Nombre: </strong>' . $certificado->legalName() . '</td>
                                    <td><img src="" style="width:30%;" alt=""></td>
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
                                    <td><strong>No. de Serie SCD: </strong>' . $str . '</td>
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
                                    <td><textarea class="form-control" style=" height: 120px;">' . wordwrap(base64_encode($signature), 75, "<br>" ,TRUE) . '</textarea></td>
                                </tr>
                            </tbody>
                        </table>
                    ';

                $dompdf = new Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
                file_put_contents('sellado.pdf', $dompdf->output());
                //$dompdf->stream('FicheroEjemplo.pdf');
                $oMerger = PDFMerger::init();
                $oMerger->addPDF('dhl.pdf', 'all');
                $oMerger->addPDF('sellado.pdf', 'all');
                $oMerger->merge();
                $oMerger->save('merged_result.pdf');

                //Firma con tcpdf
                /*$pem = 'llave.pem';
            $pemac = file_put_contents($pem, $certificado->pem());
            //pem to crt
            $comando = 'openssl req -x509 -nodes -days 365000 -newkey rsa:1024 -keyout tcpdf.crt -out tcpdf.crt';
            //dd($comando);
            exec($comando);*/
                // set certificate file
                /*$certificate = 'file://' . realpath('tcpdf.crt');
            PDF::setSignature($certificate, $certificate, 'tcpdfdemo', '', 2, $info);
            PDF::SetFont('helvetica', '', 12);
            PDF::SetTitle('Hello World');
            PDF::AddPage();
            // print a line of text
            //$text = view('tcpdf');
            // add view content
            PDF::writeHTML('Hello Worldasdasdad', true, 0, true, 0);
            // add image for signature
            PDF::Image('https://silent4business.com/wp-content/uploads/2019/06/Silent4Business-Logo-Color.png', 180, 60, 15, 15, 'PNG');
            // define active area for signature appearance
            PDF::setSignatureAppearance(180, 60, 15, 15);
            ob_end_clean();
            // save pdf file
            PDF::Output('sellado1.pdf', 'D');*/

                dd('pdf created');
            } else {
                echo 'otro', PHP_EOL;
            }
        } else {
            return redirect()->back()->with('error', 'No se han cargado los datos necesario');
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
