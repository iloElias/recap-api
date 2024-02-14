<?php

namespace Ipeweb\IpeSheets\Bootstrap;

use Ipeweb\IpeSheets\Exceptions\MissingRequiredParameterException;
use Ipeweb\IpeSheets\Internationalization\Translator;
use Ipeweb\IpeSheets\Services\JWT;
use Ipeweb\IpeSheets\Services\Utils;

class Request
{
    public static function init()
    {
        self::cors();

        // exit(json_encode(var_dump($_SERVER)));

        $lang = isset($_GET["lang"]) ? $_GET["lang"] : 'en';
        $about = isset($_SERVER["REDIRECT_URL"]) ? explode('/', $_SERVER["REDIRECT_URL"])[1] : "noSelected";

        $body = Request::getRequestBody();

        $return = match ($_SERVER['REQUEST_METHOD']) {
            'GET' => function (?string $about, $body, string $lang) {
                if (isset($_GET["message"])) {
                    if ($_GET["message"] == 'all') {
                        echo json_encode(
                            Translator::getAllFrom($lang)
                        );
                        return;
                    }

                    $message = "";
                    $params = null;

                    if (isset($_GET["params"])) {
                        $params = explode('%', $_GET["params"]);
                    }

                    $message = Translator::translate($lang, $_GET["message"], $params);

                    echo json_encode(
                        $message
                    );
                    return;
                }

                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
                $filter = isset($_GET['filter']) ? $_GET['filter'] : [];
                $field = isset($_GET['field']) ? $_GET['field'] : [];
                $sort = isset($_GET['sort']) ? $_GET['sort'] : [];

                try {
                    $header = self::getRequestHeader();

                    try {
                        if (!isset($header['Authorization'])) {
                            throw new MissingRequiredParameterException(["Header Authorization bearer"]);
                        }
                        JWT::decode($header['Authorization']);
                    } catch (\Throwable $e) {
                        http_response_code(400);
                        exit(json_encode(
                            [
                                "message" => "Invalid token sent",
                                "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                            ]
                        ));
                    }

                    $databaseClass = new ('Ipeweb\IpeSheets\Model\\' . ucfirst($about) . "Data");

                    if (!empty($field)) {
                        $fieldName = explode(':', $field)[0];
                        $fieldValue = explode(':', $field)[1];

                        $result = $databaseClass->get([$fieldName => $fieldValue]);
                        echo json_encode(JWT::encode($result, Helper::env('API_JWT_SECRET')));
                        return;
                    }

                    if (!empty($filter)) {
                        if (!empty($sort)) {
                            $sortArray = [
                                'field' => explode(":", $sort)[0],
                                'direction' => explode(":", $sort)[1]
                            ];
                            $result = $databaseClass->getSearch(($page - 1) * $perPage, $perPage, [explode(":", $filter)[0] => explode(":", $filter)[1]], $sortArray);
                        } else {
                            $result = $databaseClass->getSearch(($page - 1) * $perPage, $perPage, [explode(":", $filter)[0] => explode(":", $filter)[1]]);
                        }
                        echo json_encode(JWT::encode($result, Helper::env('API_JWT_SECRET')));
                        return;
                    } else {
                        if (!empty($sort)) {
                            $sortArray = [
                                'field' => explode(":", $sort)[0],
                                'direction' => explode(":", $sort)[1]
                            ];
                            $result = $databaseClass->getAll(($page - 1) * $perPage, $perPage, $sortArray);
                        } else {
                            $result = $databaseClass->getAll(($page - 1) * $perPage, $perPage);
                        }
                        echo json_encode(JWT::encode($result, Helper::env('API_JWT_SECRET')));
                        return;
                    }
                } catch (\Throwable $e) {
                    http_response_code(500);
                    echo json_encode(
                        [
                            "message" => Translator::translate($lang, 'not_available_service', $about, true),
                            "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                        ]
                    );
                    exit();
                }
            },
            'POST' => function (string $about, $body, string $lang) {
                try {
                    $dataClass = new ('Ipeweb\IpeSheets\Model\\' . ucfirst($about) . "Data");

                    $result = $dataClass->insert($body);

                    http_response_code(200);
                    exit(json_encode([$result]));
                } catch (\Throwable $e) {
                    http_response_code(400);
                    exit(json_encode(
                        [
                            "message" => Translator::translate($lang, 'not_available_service', $about, true),
                            "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                        ]
                    ));
                }
            },
            'PUT' => function (string $about, $body, string $lang) {
                $field = isset($_GET['field']) ? $_GET['field'] : null;
                try {
                    if (!$field) {
                        throw new \InvalidArgumentException('Missing query \'field\' param');
                    }
                    $dataClass = new ('Ipeweb\IpeSheets\Model\\' . ucfirst($about) . "Data");
                    $result = $dataClass->update(explode(':', $field)[1], $body);

                    exit(json_encode($result));
                } catch (\Throwable $e) {
                    http_response_code(400);
                    exit(json_encode(
                        [
                            "message" => Translator::translate($lang, 'not_available_service', $about, true),
                            "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                        ]
                    ));
                }
            },
            'DELETE' => function (string $about, $body, string $lang) {
                try {
                    $mapClass = new ('Ipeweb\IpeSheets\Model\\' . ucfirst($about))(id: $body["id"]);

                    foreach ($body as $key => $value) {
                        if ($key != "id") {
                            $mapClass->$key = $value;
                        }
                    }

                    if ($mapClass->validate()) {
                        $dataClass = new ('Ipeweb\IpeSheets\Model\\' . ucfirst($about) . "Data");
                        $dataClass->inactive($mapClass->id, $body);
                    }

                    echo json_encode([
                        "message" => ucfirst($about) . " has been inactivated"
                    ]);
                } catch (\Throwable $e) {
                    http_response_code();
                    exit(json_encode(
                        [
                            "message" => Translator::translate($lang, 'not_available_service', $about, true),
                            "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                        ]
                    ));
                }
            }
        };

        try {
            $return($about, $body, $lang);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            exit(json_encode(
                [
                    "message" => Translator::translate($lang, 'not_detected_problem', returnOnSupported: true),
                    "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                ]
            ));
        }
    }

    public static function getRequestBody()
    {
        $file = json_decode(file_get_contents('php://input'), true);
        if (empty($file) || !is_array($file)) {
            return [];
        }

        $data = [];

        foreach ($file[0] as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    public static function getRequestHeader()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
        return $headers;
    }

    public static function authenticateToken(string $token): bool
    {
        try {
            $token = JWT::decode($token, '');
        } catch (\Throwable $e) {
            http_response_code(500);
            exit(json_encode(
                [
                    "message" => "Invalid token sent",
                    "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                ]
            ));
        }
        return true;
    }

    public static function cors()
    {
        try {
            header('Content-Type: application/json');
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, HEAD, OPTIONS, PATCH");
            header("Content-Type: application/json; charset=UTF-8");
            header('Access-Control-Allow-Credentials: true');
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Headers: *");

            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                header('Access-Control-Allow-Origin: *');
                header("Access-Control-Allow-Headers: *");
                http_response_code(200);
                exit();
            }
        } catch (\Throwable $e) {
            exit(json_encode(
                [
                    "message" => "Something went wrong on CORS setup",
                    "error" => $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(),
                ]
            ));
        }
    }
}
