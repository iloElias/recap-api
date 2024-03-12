<?php

namespace Ipeweb\RecapSheets\Controller;

use Ipeweb\RecapSheets\Bootstrap\Request;
use Ipeweb\RecapSheets\Exceptions\MissingRequiredParameterException;
use Ipeweb\RecapSheets\Model\CardData;
use Ipeweb\RecapSheets\Model\NewProjectData;
use Ipeweb\RecapSheets\Model\ProjectData;
use Ipeweb\RecapSheets\Model\QueryGet;
use Ipeweb\RecapSheets\Model\Template\ProjectUpdate;
use Ipeweb\RecapSheets\Model\UserProjectsData;

class ProjectController
{
    public static function getUserProjects()
    {
        $query = QueryGet::getQueryItems(["field" => true]);

        $preparedParams = [];
        if (str_contains($query['field'], ':')) {
            $preparedParams = explode(':', $query['field']);
            if (isset($preparedParams[0]) and isset($preparedParams[1])) {
                $projectService = new UserProjectsData;

                try {
                    http_response_code(200);
                    return $projectService->get([$preparedParams[0] => $preparedParams[1]]);
                } catch (\Throwable) {
                    http_response_code(500);
                    throw new \Exception("Something went wrong on getting a user");
                }
            }
        }

        http_response_code(400);
        throw new \InvalidArgumentException('Invalid given "field" value. No key or value detected');
    }

    public static function postNewProject()
    {
        try {
            $projectService = new NewProjectData();
            http_response_code(201);
            return $projectService->insert(Request::$request['body']);
        } catch (\Throwable $e) {
            http_response_code(500);
            throw new \Exception('Was not possible to create a new project: ' . $e->getMessage());
        }
    }

    public static function updateProjectMd()
    {
        $projectUpdateService = new ProjectUpdate();

        try {
            return $projectUpdateService->update(Request::$request['body']);
        } catch (MissingRequiredParameterException $missE) {
            http_response_code(400);
            throw new \InvalidArgumentException('Some of the required params was not supplied: ' . $missE->getMessage());
        } catch (\Throwable $e) {
            http_response_code(500);
            throw new \Exception("Something went wrong on updating the project:" . $e->getMessage());
        }
    }

    public static function inactivateProject()
    {
        $projectUpdateService = new ProjectUpdate();

        try {
            return $projectUpdateService->delete(Request::$request['body']);
        } catch (MissingRequiredParameterException $missE) {
            http_response_code(400);
            throw new \InvalidArgumentException('Some of the required params was not supplied: ' . $missE->getMessage());
        } catch (\Throwable $e) {
            http_response_code(500);
            throw new \Exception("Something went wrong on inactivating project:" . $e->getMessage());
        }
    }

    public static function getProjectMarkdown()
    {
        $requestToken = Request::$decodedToken;
        $query = QueryGet::getQueryItems(["project_id" => true]);

        try {

            $userCanChange = new UserProjectsData();
            $userProjectResult = $userCanChange->getSearch(['user_id' => $requestToken['id'], 'project_id' => $query['project_id']], strict: true);

            if (!empty($userProjectResult)) {
                $projectService = new ProjectData();
                $projectResult = $projectService->getSearch(['id' => $query['project_id']], strict: true);

                if (!empty($projectResult)) {
                    if ($projectResult[0]['state'] === 'archived') {
                        http_response_code(400);
                        throw new \Exception('Cannot get markdown from an inactivated project');
                    }

                    $cardService = new CardData();
                    $cardResult = $cardService->getSearch(['id' => $projectResult[0]['card_id']], strict: true);
                    $projectUpdateService = new ProjectUpdate();

                    $cardResult[0]['user_permissions'] = $userProjectResult[0]['user_permissions'];
                    $cardResult[0]['name'] = $projectResult[0]['name'];
                    $cardResult[0]['imd'] = $projectUpdateService->restoreString($cardResult[0]['imd']) ?? "";

                    http_response_code(200);
                    return $cardResult;
                }
            } else if (empty($userProjectResult)) {
                $projectService = new ProjectData();
                $projectResult = $projectService->getSearch(['id' => $query['project_id']], strict: true);

                if ($projectResult) {
                    http_response_code(403);
                    throw new \Exception("User not invited");
                }
                http_response_code(404);
                throw new \Exception('Cannot get markdown from an inactivated project');
            }

            http_response_code(403);
            throw new \Exception("This user is not invited to this project");
        } catch (MissingRequiredParameterException $missE) {
            http_response_code(400);

            throw new \InvalidArgumentException('Some of the required data is missing: ' . $missE->getMessage());
        } catch (\Throwable $e) {
            http_response_code(500);

            throw new \Exception("Something went wrong on getting the project markdown: " . $e->getMessage());
        }
    }
}
