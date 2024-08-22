<?php

function reporteKM($user, $pasw) {
    include "conexion.php";

    $consulta = "SELECT hash FROM masgps.hash WHERE user='$user' AND pasw='$pasw'";
    $resultado = mysqli_query($mysqli, $consulta);
    $data = mysqli_fetch_array($resultado);
    $hash = $data['hash'];

    date_default_timezone_set("America/Santiago");
    $hoy = date("Y-m-d");
    $ayer = date('Y-m-d', strtotime("-1 days"));

    include "listado.php";

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/report/tracker/generate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'hash='.$hash.'&title=Informe%20de%20viaje&trackers='.$ids.'&from='.$ayer.'%2000%3A00%3A00&to='.$ayer.'%2023%3A59%3A59&time_filter=%7B%22from%22%3A%2200%3A00%3A00%22%2C%22to%22%3A%2223%3A59%3A59%22%2C%22weekdays%22%3A%5B1%2C2%2C3%2C4%2C5%2C6%2C7%5D%7D&plugin=%7B%22hide_empty_tabs%22%3Atrue%2C%22plugin_id%22%3A4%2C%22show_seconds%22%3Atrue%2C%22include_summary_sheet%22%3Atrue%2C%22include_summary_sheet_only%22%3Atrue%2C%22split%22%3Atrue%2C%22show_idle_duration%22%3Atrue%2C%22show_coordinates%22%3Atrue%2C%22filter%22%3Atrue%2C%22group_by_driver%22%3Afalse%7D',
        CURLOPT_HTTPHEADER => array(
            'authority: www.trackermasgps.com',
            'accept: */*',
            'accept-language: es-419,es;q=0.9,en;q=0.8',
            'content-type: application/x-www-form-urlencoded; charset=UTF-8',
            'origin: https://www.trackermasgps.com',
            'referer: https://www.trackermasgps.com/pro/applications/reports/index.html?newuiwrap=1',
            'sec-ch-ua: "Google Chrome";v="119", "Chromium";v="119", "Not?A_Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $arreglo = json_decode($response);
    $reporte = $arreglo->id;

    Loop:
    sleep(15);

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/report/tracker/retrieve',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'hash='.$hash.'&report_id='.$reporte,
        CURLOPT_HTTPHEADER => array(
            'Accept: */*',
            'Accept-Language: es-419,es;q=0.9,en;q=0.8',
            'Connection: keep-alive',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Origin: http://www.trackermasgps.com',
            'Referer: http://www.trackermasgps.com/pro/applications/reports/index.html?newuiwrap=1',
            'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $datos = json_decode($response);

    if (isset($datos->report->sheets[0]->sections[0]->data[0]->rows)) {
        $eventos = $datos->report->sheets[0]->sections[0]->data[0]->rows;

        // Dividir los eventos en lotes de 50
        $eventosLotes = array_chunk($eventos, 50);

        foreach ($eventosLotes as $lote) {
            $qryBatch = "";

            foreach ($lote as $evento) {
                $avg_speed = $evento->avg_speed->v;
                $count = $evento->count->v;
                $length = $evento->length->v;
                $max_speed = $evento->max_speed->v;
                $time = $evento->time->v;
                $patente = $evento->object->v;
                $idle_duration = $evento->idle_duration->v;

                $qryBatch .= "INSERT INTO `masgps`.`kmpullman` (`cuenta`, `avg_speed`, `patente`, `fecha`, `count`, `max_speed`, `time`, `idle_duration`, `length`) 
                VALUES ('$user', '$avg_speed', '$patente', '$ayer', '$count', '$max_speed', '$time', '$idle_duration', '$length');";
            }

            // Ejecutar las consultas en paralelo
            if ($qryBatch != "") {
                $ejecutar = mysqli_multi_query($mysqli, $qryBatch);

                // Esperar a que finalicen todas las consultas
                while (mysqli_more_results($mysqli)) {
                    mysqli_next_result($mysqli);
                }
            }
        }
    } else {
        goto Loop;
    }

    echo 'Registros  ok';
}


