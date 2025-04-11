<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

$sportParam = $argv[1] ?? 'all'; // Si no existe el primer parámetro, se asigna 'all'
$countryParam = $argv[2] ?? 'all'; // Si no existe el segundo parámetro, se asigna 'all'

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$apiKey = "3";
if (isset($_ENV['API_KEY'])) {
    echo "Using .env API_KEY\n";
    $apiKey=$_ENV['API_KEY'];
}
$apiBase = "https://www.thesportsdb.com/api/v1/json/$apiKey/";

// Función auxiliar para obtener JSON de la API y decodificarlo
function fetchJson($url) {
    $start=time();
    $json = @file_get_contents($url);
    if ($json === FALSE) {
        // No se pudo obtener respuesta
        return null;
    }
    $data = json_decode($json, true);
    if ($data === null) {
        // JSON inválido o error de decodificación
        return null;
    }
    $end=time();
    $elapsed=$start-$end;
    echo "Called url $url retrieved in $elapsed seconds\n";
    return $data;
}

// 1. Obtener la lista de todos los deportes disponibles
echo "Getting all sports\n";
$sportsData = fetchJson($apiBase . "all_sports.php");
if (!$sportsData || !isset($sportsData['sports'])) {
    die("Error: No se pudo obtener la lista de deportes.\n");
}
echo "DONE! ".count($sportsData)." records found\n";
$jsonContent = json_encode($sportsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents('sports.json', $jsonContent);
//die(print_r($sportsData['sports'], true));

// 2. Obtener la lista de todos los países disponibles
echo "Getting all countries\n";
$countriesData = fetchJson($apiBase . "all_countries.php");
$countryList = [];
if ($countriesData && isset($countriesData['countries'])) {
    foreach ($countriesData['countries'] as $country) {
        // Tomar el nombre del país en inglés (asumimos 'name_en' como clave del nombre de país)
        if (isset($country['name_en'])) {
            $countryList[] = $country['name_en'];
        } elseif (isset($country['name'])) {
            $countryList[] = $country['name'];
        }
    }
}
echo "DONE! ".count($countryList)." records found\n";
$jsonContent = json_encode($countryList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents('countries.json', $jsonContent);

// Asegurar que tenemos una lista de países para iterar
if (empty($countryList)) {
    die("Error: No se pudo obtener la lista de países.\n");
}

if ($sportParam!='all') {
    echo "Filtering sport=$sportParam\n";
    $found=false;
    foreach ($sportsData['sports'] as $sport) {
        $sportName = $sport['strSport'] ?? null;
        if (strtolower($sportName)==strtolower($sportParam)) {
            $found=true;
            break;
        }
    }
    if (!$found) die("Sport not found $sportParam\n");
}

if ($countryParam!='all') {
    echo "Filtering country=$countryParam\n";
    $found=false;
    foreach ($countryList as $country) {
        if (strtolower($country)==strtolower($countryParam)) {
            $found=true;
            break;
        }
    }
    if (!$found) die("Country not found $countryParam\n");
}
sleep(3);

// Recorrer cada deporte
foreach ($sportsData['sports'] as $sport) {
    $sportName = $sport['strSport'] ?? null;
    if (!$sportName) continue;
    //if ($sportName=='Soccer') continue;
    //if ($sportName!='Basketball') continue;
    if ($sportParam!='all' && strtolower($sportName)!=strtolower($sportParam)) continue;

    // Crear directorio para el deporte si no existe
    if (!is_dir($sportName)) {
        mkdir($sportName, 0777, true);
    }

    // 2. Para cada deporte, iterar por los países para encontrar ligas de ese deporte
    foreach ($countryList as $countryName) {
        //if ($countryName!='Spain') continue;
        if ($countryParam!='all' && strtolower($countryName)!=strtolower($countryParam)) continue;

        echo "Processing sport $sportName and country $countryName\n";
        // URL para buscar todas las ligas en un país dado para el deporte actual
        $leaguesUrl = $apiBase . "search_all_leagues.php?c=" . urlencode($countryName) . "&s=" . urlencode($sportName);
        $leaguesData = fetchJson($leaguesUrl);
        if (!$leaguesData) {
            // Si hay un error en la llamada, pasar al siguiente país
            continue;
        }
        // La respuesta de search_all_leagues devuelve las ligas en 'countries' (o 'countrys')
        $leaguesList = $leaguesData['countries'] ?? $leaguesData['countrys'] ?? null;
        if (!$leaguesList) {
            // Si no hay ligas para este deporte en el país actual, continuar con el próximo país
            continue;
        }

        // Recorrer cada liga encontrada para este país y deporte
        foreach ($leaguesList as $league) {
            $leagueName = $league['strLeague'] ?? null;
            if (!$leagueName) continue;

            // Nombre del país (de los datos, por seguridad)
            $leagueCountry = $league['strCountry'] ?? $countryName;
            // Asegurarse de que el país coincide con la carpeta actual esperada
            $countryDir = "$sportName/$leagueCountry";
            // Crear la carpeta del país (dentro del deporte) si no existe
            if (!is_dir($countryDir)) {
                mkdir($countryDir, 0777, true);
            }
            // Crear la carpeta de la liga dentro de deporte/país
            $leagueDir = "$countryDir/$leagueName";
            if (!is_dir($leagueDir)) {
                mkdir($leagueDir, 0777, true);
            } else {
                echo "Folder $countryDir/$leagueName already exists, skipping\n";
                continue;
            }

            // 4. Obtener todos los equipos de la liga actual
            $teamsUrl = $apiBase . "search_all_teams.php?l=" . urlencode($leagueName);
            $teamsData = fetchJson($teamsUrl);
            if (!$teamsData || !isset($teamsData['teams']) || $teamsData['teams'] === null) {
                // Si la liga no tiene equipos (o error), continuar con la siguiente liga
                continue;
            }
            $teamsList = $teamsData['teams'];

            // Array para almacenar información de equipos (opcionalmente se puede depurar campos)
            // En este caso guardaremos todos los datos tal cual vienen de la API.
            $teamsInfo = $teamsList; 

            // Recorrer equipos para descargar el escudo de cada uno
            foreach ($teamsList as $team) {
                $teamName = $team['strTeam'] ?? 'Equipo';
                $teamId   = $team['idTeam'] ?? '';
                echo "Processing team $teamName\n";
                // Obtener URL del escudo
                $badgeUrl = $team['strTeamBadge'] ?? $team['strTeamLogo'] ?? $team['strBadge'] ?? null;
                if (!$badgeUrl) {
                    // Si no hay URL de escudo, pasar al siguiente equipo
                    continue;
                }
                // Nombre de archivo para el escudo: usar el id del equipo si disponible, sino el nombre
                $ext = pathinfo(parse_url($badgeUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (!$ext) $ext = 'png';  // suponer png si no se obtiene extensión
                $fileName = $teamId ? "{$teamId}.{$ext}" : preg_replace('/[^A-Za-z0-9_-]/', '_', $teamName) . ".$ext";
                $filePath = "$leagueDir/$fileName";
                // 5. Evitar descargar duplicados: comprobar si ya existe el archivo
                if (file_exists($filePath)) {
                    // Ya descargado previamente, saltar descarga
                    continue;
                }
                // Descargar la imagen del escudo
                $imgData = @file_get_contents($badgeUrl);
                if ($imgData === FALSE) {
                    // Si falla la descarga, omitir este equipo
                    continue;
                }
                // Guardar la imagen en el archivo correspondiente
                file_put_contents($filePath, $imgData);
            }

            // 6. Generar un archivo JSON dentro de la carpeta de la liga con la información de los equipos
            $jsonFilePath = "$leagueDir/teams.json";
            // Codificar a JSON (con formato legible)
            $jsonContent = json_encode(['teams' => $teamsInfo], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($jsonFilePath, $jsonContent);
        } // fin foreach liga
    } // fin foreach país
} // fin foreach deporte

echo "Proceso completado.\n";