<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminAbWatermarkBatchController extends ModuleAdminController
{
    /** @var AbWatermark */
    public $module;

    public function ajaxProcessStart()
    {
        try {
            $this->sendJson(['success' => true, 'status' => $this->module->startBatch((string) Tools::getValue('mode'))]);
        } catch (Throwable $exception) {
            $this->sendJson(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function ajaxProcessStep()
    {
        try {
            $this->sendJson(['success' => true, 'status' => $this->module->processBatchStep(3)]);
        } catch (Throwable $exception) {
            $this->sendJson(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function ajaxProcessStatus()
    {
        $this->sendJson(['success' => true, 'status' => $this->module->getBatchStatus()]);
    }

    private function sendJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode($payload));
    }
}
