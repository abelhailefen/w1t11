<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class ReportExportService
{
    public function exportToCsv(array $rows): array
    {
        $path = tempnam(sys_get_temp_dir(), 'report_') . '.csv';
        $fp = fopen($path, 'wb');
        if (!$fp) {
            throw new ApiException('Unable to create CSV', 500);
        }
        if (!empty($rows)) {
            fputcsv($fp, array_keys((array) $rows[0]));
            foreach ($rows as $row) {
                $values = array_map(function ($value) {
                    if ($value instanceof \BackedEnum) {
                        return $value->value;
                    }
                    if ($value instanceof \UnitEnum) {
                        return $value->name;
                    }
                    if (is_object($value) && method_exists($value, '__toString')) {
                        return (string) $value;
                    }
                    return $value;
                }, array_values((array) $row));
                fputcsv($fp, $values);
            }
        }
        fclose($fp);

        return ['path' => $path, 'mime' => 'text/csv', 'filename' => 'report.csv'];
    }

    public function exportToPdf(array $dashboardData): array
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);

        $html = '<h1>Compliance Report</h1><table border="1" cellpadding="6" cellspacing="0">';
        foreach ($dashboardData as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }
            $html .= '<tr><th style="text-align:left">' . htmlspecialchars((string) $key) . '</th><td>' . htmlspecialchars((string) $value) . '</td></tr>';
        }
        $html .= '</table>';
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $path = tempnam(sys_get_temp_dir(), 'report_') . '.pdf';
        file_put_contents($path, $dompdf->output());

        return ['path' => $path, 'mime' => 'application/pdf', 'filename' => 'compliance-report.pdf'];
    }
}
