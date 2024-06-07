<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use App\Service\CompareService; // Custom service that help comparing datas

class ApiController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(string $message = null): JsonResponse
    {
        $message = "Welcome to the REST API of this project, to use the API, you can paste this after your DNS: /api/putACityHere1/putACityHere2. It will returns a lot of weather informations about the two cities provided, enjoy!";

        return $this->json($message);
    }

    #[Route('/api/{city1}/{city2}', name: 'app_api')]
    public function compare(string $city1, string $city2, ParameterBagInterface $parameterBag, CompareService $compare): JsonResponse
    {
        $responseArray = [];
        // FETCH OpenWeather map API here
        $url_base = "https://api.openweathermap.org/data/2.5/";
        $api_key = $parameterBag->get("API_KEY_SECRET");

        // OpenWeatherMap API Calls
        $query1 = file_get_contents($url_base . "weather?q=" . $city1 . "&units=metric&appid=" . $api_key, true);
        $query2 = file_get_contents($url_base . "weather?q=" . $city2 . "&units=metric&appid=" . $api_key, true);

        $cities = [json_decode($query1), json_decode($query2)];

        $query3 = file_get_contents($url_base . "forecast?lat=" . $cities[0]->coord->lat . "&lon=" . $cities[0]->coord->lon . "&units=metric&appid=" . $api_key, true);
        $query4 = file_get_contents($url_base . "forecast?lat=" . $cities[1]->coord->lat . "&lon=" . $cities[1]->coord->lon . "&units=metric&appid=" . $api_key, true);

        $citiesAll = [json_decode($query3), json_decode($query4)]; // Decode JSON queries

        $responseArray['cities'] = $cities; // Cities
        $responseArray['citiesAll'] = $citiesAll; // Cities full informations (5 days long)

        // Algorythm to compare
        $compareData = []; // This array contains the average of their weather values and the score
        $compareData['city1'] = ['name' => null, 'temp' => null, 'humidity' => null, 'clouds' => null, 'score' => 0];
        $compareData['city2'] = ['name' => null, 'temp' => null, 'humidity' => null, 'clouds' => null, 'score' => 0];

        // if the city name of the first queries are the same
        if ($responseArray['cities'][0]->name == $responseArray['citiesAll'][0]->city->name) {
            $compareData['city1']['name'] = $responseArray['citiesAll'][0]->city->name; // add names on the compareData array
            $compareData['city2']['name'] = $responseArray['citiesAll'][1]->city->name; // add names on the compareData array

            $listSize = count($responseArray['citiesAll'][0]->list); // size of the array

            // For loop that takes every total of the two cities
            for ($i = 0; $i < $listSize; $i++) {
                // First city temp total
                $totaltemp1 = 0;
                $totaltemp1 += $responseArray['citiesAll'][0]->list[$i]->main->temp * $listSize;
                // Second city temp total
                $totaltemp2 = 0;
                $totaltemp2 += $responseArray['citiesAll'][1]->list[$i]->main->temp * $listSize;
                // First city humidity total
                $totalhum1 = 0;
                $totalhum1 += $responseArray['citiesAll'][0]->list[$i]->main->humidity * $listSize;
                // First city humidity total
                $totalhum2 = 0;
                $totalhum2 += $responseArray['citiesAll'][1]->list[$i]->main->humidity * $listSize;
                // First city clouds rate total
                $total1cl = 0;
                $total1cl += $responseArray['citiesAll'][0]->list[$i]->clouds->all * $listSize;
                // First city humidity total
                $total2cl = 0;
                $total2cl += $responseArray['citiesAll'][1]->list[$i]->main->humidity * $listSize;
            }

            // COMPARING THE TEMPERATURE
            $cit1tempmoy = $totaltemp1 / $listSize;
            // $cit1tempmoy = $cit1tempmoy - 27; // Offset of the average
            $compare->calculateOffset($cit1tempmoy, 27);

            $cit2tempmoy = $totaltemp2 / $listSize;
            $compare->calculateOffset($cit2tempmoy, 27);

            // if cit1tempmoy value is below 0, make it positive
            // if ($cit2tempmoy < 0) {
            //     $cit2tempmoy = $cit2tempmoy * -1;
            // }
            $compare->ifValBelowZero($cit1tempmoy);

            // if cit2tempmoy value is below 0, make it positive
            $compare->ifValBelowZero($cit2tempmoy);

            // inject average values of temp
            $compareData['city1']['temp'] = $cit1tempmoy;
            $compareData['city2']['temp'] = $cit2tempmoy;

            // if cit1tempmoy value has the lowest difference
            if ($cit1tempmoy < $cit2tempmoy) {
                $compareData['city1']['score'] = $compareData['city1']['score'] + 20;
            }

            // if cit2moy value has the lowest difference
            if ($cit2tempmoy < $cit1tempmoy) {
                $compareData['city2']['score'] = $compareData['city2']['score'] + 20;
            }


            // COMPARING THE HUMIDITY
            $cit1hummoy = $totalhum1 / $listSize;
            $compare->calculateOffset($cit1hummoy, 60);

            $cit2hummoy = $totalhum2 / $listSize;
            $compare->calculateOffset($cit2hummoy, 60);

            // if cit1hummoy value is below 0, make it positive
            $compare->ifValBelowZero($cit1hummoy);

            // if cit2hummoy value is below 0, make it positive
            $compare->ifValBelowZero($cit2hummoy);

            // inject average values of hum
            $compareData['city1']['humidity'] = $cit1hummoy;
            $compareData['city2']['humidity'] = $cit2hummoy;

            // if cit1hummoy value has the lowest difference
            if ($cit1hummoy < $cit2hummoy) {
                $compareData['city1']['score'] = $compareData['city1']['score'] + 15;
            }

            // if cit2moy value has the lowest difference
            if ($cit2hummoy < $cit1hummoy) {
                $compareData['city2']['score'] = $compareData['city2']['score'] + 15;
            }


            // COMPARING THE CLOUDS RATE
            $cit1clmoy = $total1cl / $listSize;
            $compare->calculateOffset($cit1clmoy, 15);

            $cit2clmoy = $total2cl / $listSize;
            $compare->calculateOffset($cit2clmoy, 15);

            // if cit1clmoy value is below 0, make it positive
            $compare->ifValBelowZero($cit1clmoy);

            // if cit2clmoy value is below 0, make it positive
            $compare->ifValBelowZero($cit2clmoy);

            // inject average values of hum
            $compareData['city1']['clouds'] = $cit1clmoy;
            $compareData['city2']['clouds'] = $cit2clmoy;

            // if cit1clmoy value has the lowest difference
            if ($cit1clmoy < $cit2clmoy) {
                $compareData['city1']['score'] = $compareData['city1']['score'] + 10;
            }

            // if cit2moy value has the lowest difference
            if ($cit2clmoy < $cit1clmoy) {
                $compareData['city2']['score'] = $compareData['city2']['score'] + 10;
            }

            // START DEBUG ZONE

            $arr = get_defined_vars();
            $arr;
            // dd($compareData, $arr);

            // END DEBUG ZONE

            if ($compareData['city1']['score'] > $compareData['city2']['score']) {
                $responseArray['winner'] = $compareData['city1']['name']; // Winner!
            } elseif ($compareData['city2']['score'] > $compareData['city1']['score']) {
                $responseArray['winner'] = $compareData['city2']['name']; // Winner!
            }
        } else {
            null;
        }

        // if ($compareData['city1']['name'] == $responseArray['winner']) {
        //     $rep = "good";
        // } else {
        //     $rep = "not good";
        // }

        if ($compareData['city1']['name'] == $responseArray['winner']) {
            null;
        } else {
            $temp1 = $compareData['city1'];

            $compareData['city1'] = $compareData['city2'];
            $compareData['city2'] = $temp1;
        }

        // if ($compareData['city1']['name'] == $responseArray['winner']) {
        //     $rep = "good";
        // } else {
        //     $rep = "not good";
        // }

        // dd($compareData, $responseArray, $rep);

        return $this->json([$responseArray, $compareData]);
    }
}
