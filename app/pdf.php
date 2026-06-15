<?php
declare(strict_types=1);

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

require_once __DIR__ . "/../config.php";

/**
 * Genera el PDF de una papeleta colocando texto encima del PDF plantilla
 * mediante FPDI (importa la página) + FPDF (dibuja encima).
 *
 * Las coordenadas están en milimetros, origen arriba-izquierda (0,0).
 * FPDF por defecto usa origen abajo-izquierda; aquí convertimos con
 *    pdf_y = page_height_mm - y_mm
 * Ajusta los valores del array $POS si algún campo se desvía.
 */
final class PapeletaPDF
{
    /** Coordenadas absolutas en mm. Editables si la plantilla cambia. */
    private const POS = [
        // ----------------------- PAPELETA DE SALIDA (izquierda, x: 0-105) -----
        "numero" => ["x" => 87, "y" => 7.5, "size" => 9],
        "fecha" => ["x" => 87, "y" => 12.5, "size" => 9],
        "dni" => ["x" => 87, "y" => 17, "size" => 9],
        "apellidos_nombres" => ["x" => 38, "y" => 25.5, "size" => 9],
        "regimen_laboral" => ["x" => 38, "y" => 30, "size" => 9],
        "dependencia" => ["x" => 38, "y" => 35, "size" => 9],
        "cargo" => ["x" => 38, "y" => 40, "size" => 9],

        // Checkboxes de motivo - dibujamos una X sobre el cuadradito
        "mot_capacitacion" => ["x" => 93, "y" => 57.5, "check" => true],
        "mot_citacion" => ["x" => 93, "y" => 61.5, "check" => true],
        "mot_comision" => ["x" => 93, "y" => 64, "check" => true],
        "mot_enfermedad" => ["x" => 93, "y" => 67, "check" => true],
        "mot_maternidad" => ["x" => 93, "y" => 69.5, "check" => true],
        "mot_particulares" => ["x" => 93, "y" => 72.5, "check" => true],

        "fundamentacion" => ["x" => 10, "y" => 82.5, "size" => 9],
        "lugar" => ["x" => 48, "y" => 90, "size" => 9],

        "dia" => ["x" => 9, "y" => 103, "size" => 9],
        "mes" => ["x" => 18, "y" => 103, "size" => 9],
        "anio_dmy" => ["x" => 26, "y" => 103, "size" => 9],
        "hora_salida" => ["x" => 57, "y" => 98, "size" => 9],
        "hora_retorno" => ["x" => 93, "y" => 98, "size" => 9],

        "retorna_si" => ["x" => 82, "y" => 105, "check" => true],
        "retorna_no" => ["x" => 94, "y" => 105, "check" => true],

        "observaciones" => ["x" => 30, "y" => 111, "size" => 9],

        // ----------------------- PAPELETA DE RETORNO (derecha, x: 110-200) ------
        "numero_ret" => ["x" => 185, "y" => 7.5, "size" => 9],
        "fecha_ret" => ["x" => 185, "y" => 12.5, "size" => 9],
        "dni_ret" => ["x" => 185, "y" => 17, "size" => 9],

        "apellidos_nombres_ret" => ["x" => 152, "y" => 25.5, "size" => 9],
        "regimen_ret" => ["x" => 145, "y" => 30, "size" => 9],
        "dependencia_ret" => ["x" => 145, "y" => 35, "size" => 9],
        "cargo_ret" => ["x" => 145, "y" => 40, "size" => 9],

        "observaciones_ret" => ["x" => 124, "y" => 55, "size" => 9],
        "lugar_visita" => ["x" => 153, "y" => 75, "size" => 9],
    ];

    private const MOTIVO_CODES = [
        "capacitacion" => "CAPACITACION OFICIALIZADA",
        "citacion" => "CITACIÓN JUDICIAL, MILITAR O POLICIAL",
        "comision" => "COMISIÓN DE SERVICIO",
        "enfermedad" => "ENFERMEDAD ATENCION ESSALUD",
        "maternidad" => "MATERNIDAD - LACTANCIA",
        "particulares" => "MOTIVOS PARTICULARES (TRÁMITES PERSONALES)",
    ];

    public static function motivoCodigoALabel(string $codigo): string
    {
        return self::MOTIVO_CODES[$codigo] ?? $codigo;
    }

    public static function motivoLabelACodigo(string $label): ?string
    {
        $label = mb_strtoupper(trim($label));
        foreach (self::MOTIVO_CODES as $cod => $lab) {
            if ($lab === $label) {
                return $cod;
            }
        }
        return null;
    }

    public static function motivoOpciones(): array
    {
        return self::MOTIVO_CODES;
    }

    /**
     * @param array $p  Datos de la papeleta (claves coinciden con POS)
     * @param string $dest 'D' = download, 'F' = save, 'I' = inline
     * @param string|null $outfile
     * @return string Contenido del PDF si $dest === 'S'
     */
    public static function generar(
        array $p,
        string $dest = "D",
        ?string $outfile = null,
    ): string {
        if (!is_readable(PLANTILLA_PDF)) {
            throw new RuntimeException(
                "Plantilla PDF no encontrada: " . PLANTILLA_PDF,
            );
        }

        $pdf = new Fpdi();
        $pdf->setSourceFile(PLANTILLA_PDF);
        $tpl = $pdf->importPage(1);

        $size = $pdf->getTemplateSize($tpl);
        $pageW = $size["width"];
        $pageH = $size["height"];

        // Desactivar auto page break: NO queremos que FPDF cambie de pagina
        $pdf->SetAutoPageBreak(false);
        // Sin margenes: controlamos nosotros las posiciones exactas
        $pdf->SetMargins(0, 0, 0);

        $pdf->AddPage($size["orientation"], [$pageW, $pageH]);
        $pdf->useTemplate($tpl, 0, 0, $pageW, $pageH);

        // IMPORTANTE: en FPDI/FPDF el origen (0,0) es la esquina SUPERIOR IZQUIERDA.
        // Las coordenadas del array POS son mm desde arriba.
        $drawText = function (Fpdi $pdf, string|int|null $text, array $p) {
            $pdf->SetFont(
                "Helvetica",
                !empty($p["bold"]) ? "B" : "",
                $p["size"] ?? 10,
            );
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY((float) $p["x"], (float) $p["y"]);
            $pdf->Write(0, (string) ($text ?? ""));
        };

        $drawCheck = function (Fpdi $pdf, array $p) {
            $pdf->SetFont("Helvetica", "B", 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY((float) $p["x"] - 1.5, (float) $p["y"] - 1.5);
            $pdf->Write(0, "X");
        };

        $pos = self::POS;

        // ---- Bloque SALIDA ----
        $drawText($pdf, $p["numero"], $pos["numero"]);
        $drawText($pdf, $p["fecha"], $pos["fecha"]);
        $drawText($pdf, $p["dni"], $pos["dni"]);

        $drawText($pdf, $p["apellidos_nombres"], $pos["apellidos_nombres"]);
        $drawText($pdf, $p["regimen_laboral"] ?? "", $pos["regimen_laboral"]);
        $drawText($pdf, $p["dependencia"], $pos["dependencia"]);
        $drawText($pdf, $p["cargo"], $pos["cargo"]);

        if (!empty($p["motivo_salida"])) {
            $map = [
                "capacitacion" => "mot_capacitacion",
                "citacion" => "mot_citacion",
                "comision" => "mot_comision",
                "enfermedad" => "mot_enfermedad",
                "maternidad" => "mot_maternidad",
                "particulares" => "mot_particulares",
            ];
            if (isset($map[$p["motivo_salida"]])) {
                $drawCheck($pdf, $pos[$map[$p["motivo_salida"]]]);
            }
        }

        $drawText($pdf, $p["fundamentacion"] ?? "", $pos["fundamentacion"]);
        $drawText($pdf, $p["lugar"] ?? "", $pos["lugar"]);

        $drawText($pdf, $p["dia"] ?? "", $pos["dia"]);
        $drawText($pdf, $p["mes"] ?? "", $pos["mes"]);
        $drawText($pdf, $p["anio_dmy"] ?? "", $pos["anio_dmy"]);
        $drawText(
            $pdf,
            $p["hora_salida"] ? substr($p["hora_salida"], 0, 5) : "",
            $pos["hora_salida"],
        );
        $drawText(
            $pdf,
            $p["hora_retorno"] ? substr($p["hora_retorno"], 0, 5) : "",
            $pos["hora_retorno"],
        );

        if (($p["retorna"] ?? "NO") === "SI") {
            $drawCheck($pdf, $pos["retorna_si"]);
        } else {
            $drawCheck($pdf, $pos["retorna_no"]);
        }

        $drawText($pdf, $p["observaciones"] ?? "", $pos["observaciones"]);

        // ---- Bloque RETORNO ----
        $drawText($pdf, $p["numero"], $pos["numero_ret"]);
        $drawText($pdf, $p["fecha"], $pos["fecha_ret"]);
        $drawText($pdf, $p["dni"], $pos["dni_ret"]);
        $drawText($pdf, $p["apellidos_nombres"], $pos["apellidos_nombres_ret"]);
        $drawText(
            $pdf,
            $p["regimen"] ?? ($p["regimen_laboral"] ?? ""),
            $pos["regimen_ret"],
        );
        $drawText($pdf, $p["dependencia"], $pos["dependencia_ret"]);
        $drawText($pdf, $p["cargo"], $pos["cargo_ret"]);

        // Salida
        if ($dest === "S") {
            return $pdf->Output("S", $outfile ?? "papeleta.pdf");
        }
        $pdf->Output(
            $dest,
            $outfile ?? "papeleta-" . ($p["numero"] ?? "sin-numero") . ".pdf",
        );
        exit();
    }
}
