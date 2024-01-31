<?php

namespace Ipeweb\IpeSheets\Model;

use Ipeweb\IpeSheets\Model\ModelHandler;

class ProjectData
{
    protected string $table = 'projects';
    protected array $fields = ['id', 'name', 'card_id', 'type'];
    private ModelHandler $dataHandler;

    public function __construct()
    {
        $this->dataHandler = ModelHandler::getModelHandlerInstance($this->table, $this->fields);
    }

    public function insert(array $data): int
    {
        return $this->dataHandler->insert($data);
    }

    public function get(array $data): array
    {
        return $this->dataHandler->get($data);
    }

    public function getSearch(int $offset = 1, int $limit = 25, array $data, array $order = null): array
    {
        return $this->dataHandler->getSearch($offset, $limit, $data, $order);
    }

    public function getAll(int $offset = 1, int $limit = 25, array $order = null): array
    {
        return $this->dataHandler->getAll($offset, $limit, $order);
    }

    public function update(int $id, array $data)
    {
        return $this->dataHandler->update($id, $data);
    }
    public function inactive(int $id)
    {
        return $this->dataHandler->inactive($id);
    }
}
