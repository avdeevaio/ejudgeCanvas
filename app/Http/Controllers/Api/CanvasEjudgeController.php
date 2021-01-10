<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CanvasEjudgeController extends Controller
{
    /**
     * Start integration.
     *
     * @return \Illuminate\Http\Response
     */
    public function startIntegration(Request $request) {

        // validation
        if ($request->has('course'))
        {
            $course = $request->input('course'); //1269;


            if ($request->has('contests_assigns'))
            {
                // convert input contests_assigns param to array
                $contestsAssignsStr = explode(", ", $request->input('contests_assigns'));
                $contestsAssigns = [];
                foreach($contestsAssignsStr as $str)
                {
                    list($key, $value) = explode(' => ', $str);
                    $keyInt = (int)$key;
                    $valInt = (int)$value;

                    $contestsAssigns[$key] = $valInt;
                }

                try
                {
                    $canvasStudents = self::loadCanvasStudents($course);

                    $errorPutArr = [];

                    foreach ($canvasStudents as $canvasStudent)
                    {
                        foreach ($contestsAssigns as $contest => $assign)
                        {
                            $total = DB::connection('mysql_ejudge')
                            					->select("select COUNT(DISTINCT prob_id) as total
                                                        from runs r2
                                                        inner join logins l on r2.user_id = l.user_id
                                                        where contest_id = " . $contest ."
                                                        and l.login = '" . $canvasStudent["login_id"] ."'
                                                        and status = 0
                                                        ")[0]->total;
                            $currStudent = [
                                "contest" => (int)$contest,
                                "student_login" => $canvasStudent["login_id"],
                                "total" => $total
                            ];

                            // if ($total > 8) $total = 8;

                            if ($total > 0)
                            {
                                try {
                                    self::setCanvasStudentsTotal($course, $canvasStudent["id"], $assign, $total);
                                }
                                catch (\Exception $e)
                                {                                                   // if unexpected query error
                                    array_push($errorPutArr, $currStudent);
                                }
                            }
                        }
                    }

                    if (! empty($errorPutArr))
                    {   
                                                                   // if found error
                        return response()->json($errorPutArr, 400);
                    }
                    else
                    {                                                           // if method Success
                        return response()->json([
                            "status" => "ok"
                        ], 200);
                    }
                }
                catch (\Exception $e)
                {                                                               // if unexpected query error
                    return response()->json([
                        "error" => $e->getMessage(),
                        "message" => "Ошибка выполнения запроса"
                    ], 400);
                }

            }
            else
            {
                return response()->json([
                    "status" => "error",
                    "message" => 'Массив заданий не задан'
                ], 422);
            }

        }
        else
        {
            return response()->json([
                "status" => "error",
                "message" => 'Id курса не задан'
            ], 422);
        }

    }

    /**
     * Get resources list from canvas api.
     *
     * @return array
     */
    private function loadCanvasStudents(int $courseId) {

        $canvas = config('services.canvas.master_token');

        $client = new Client();
        $url = 'https://canvas.letovo.ru/api/v1/courses/' . $courseId .'/students';
        $response = $client->request(
            'GET',
            $url,
            [
                'headers' => ['Authorization' => 'Bearer '. $canvas],
                'verify'  => false
            ]);

        return json_decode($response->getBody()->getContents(),true);
    }

    /**
     * Set user total to canvas api.
     *
     * @return array
     */
    private function setCanvasStudentsTotal(int $courseId, int $userId, int $assigmId, int $total) {

        $canvas = config('services.canvas.master_token');

        $client = new Client();
        $url = 'https://canvas.letovo.ru/api/v1/courses/' . $courseId .'/assignments/'
            . $assigmId . '/submissions/' . $userId . '?submission[posted_grade]=' . $total;

        $response = $client->request(
            'PUT',
            $url,
            [
                'headers' => ['Authorization' => 'Bearer ' . $canvas],
                'verify'  => false
            ]);

        return json_decode($response->getBody()->getContents(),true);
    }
}
