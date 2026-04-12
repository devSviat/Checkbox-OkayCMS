<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;

/** Робота зі змінами касира: відкриття, закриття, синхронізація з Checkbox API */
class CheckboxShiftsHelper extends CheckboxApiHelper
{
    /**
     * Прив'язує $accessToken і $errors за посиланням до іншого хелпера,
     * щоб усі хелпери поділяли один токен і стан помилок.
     */
    public function syncWithHelper(CheckboxApiHelper $helper): void
    {
        $this->accessToken = &$helper->accessToken;
        $this->errors = &$helper->errors;
    }

    /** @return array|false */
    public function createShift()
    {
        $this->clearErrors();
        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            if (!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $response = $this->makeApiRequest('shifts', [], ['method' => 'POST']);
        if (empty($this->errors) && is_array($response)) {
            $this->saveShiftToDatabase($response);
        }
        return $response;
    }

    /** @return array|false */
    public function checkShiftStatus(string $shiftId)
    {
        $this->clearErrors();
        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            if (!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $response = $this->makeApiRequest('shifts/' . $shiftId, [], ['method' => 'GET']);
        if (empty($this->errors) && is_array($response)) {
            $this->saveShiftToDatabase($response);
        }
        return $response;
    }

    /**
     * Cron-задача: закриває застарілі зміни (відкриті не сьогодні),
     * оновлює статус CLOSING-змін і синхронізує останні зміни з API.
     */
    public function cronCheckShifts(): void
    {
        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);

        // Закриваємо зміну відкриту не сьогодні
        $expiredShift = $shiftsEntity->findOne(['opened' => 1, 'expired' => true]);
        if ($expiredShift && !empty($expiredShift->shift_id)) {
            $this->closeShift();
        }

        // Оновлюємо зміни в перехідному статусі CLOSING
        foreach ($shiftsEntity->find(['status' => 'CLOSING']) as $shift) {
            if (!empty($shift->shift_id)) {
                $this->checkShiftStatus($shift->shift_id);
            }
        }

        // Синхронізуємо останні зміни з API
        $response = $this->getShiftsList();
        if (!empty($this->errors)) {
            return;
        }
        if (!is_array($response) || !isset($response['results']) || !is_array($response['results'])) {
            return;
        }
        foreach ($response['results'] as $shift) {
            if (is_array($shift)) {
                $this->saveShiftToDatabase($shift);
            }
        }
    }

    /** @return array|false */
    public function getShiftsList()
    {
        $this->clearErrors();
        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            if (!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        return $this->makeApiRequest('shifts', ['desc' => true, 'limit' => 5], ['method' => 'GET']);
    }

    /** @return array|false */
    public function closeShift()
    {
        $this->clearErrors();
        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            if (!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $response = $this->makeApiRequest('shifts/close', [], ['method' => 'POST']);
        if (empty($this->errors) && is_array($response)) {
            $this->saveShiftToDatabase($response);

            $shiftId = isset($response['id']) ? (string)$response['id'] : null;
            if ($shiftId && isset($response['status']) && strtoupper($response['status']) === 'CLOSING') {
                $this->waitForShiftClosed($shiftId);
            }
        }
        return $response;
    }

    /**
     * Відкриває зміну, якщо немає жодної активної.
     * Викликати тільки коли є реальні операції — щоб не відкривати зміну даремно.
     * Після виклику createShift очікує переходу в статус OPENED.
     *
     * @return object|null Активна зміна або null при помилці відкриття
     */
    public function openShiftIfNeeded(int $maxWaitAttempts = 5, int $waitSeconds = 2): ?object
    {
        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        $activeShift = $shiftsEntity->getActiveShift();

        if ($activeShift) {
            // Якщо зміна застрягла у статусі CREATED — оновлюємо з API
            if ($activeShift->status === 'CREATED') {
                $this->checkShiftStatus($activeShift->shift_id);
                $activeShift = $shiftsEntity->getActiveShift();
            }
            return $activeShift ?: null;
        }

        $response = $this->createShift();
        if (!empty($this->errors) || !is_array($response)) {
            return null;
        }

        $shiftId = isset($response['id']) ? (string)$response['id'] : null;
        if (!$shiftId) {
            return null;
        }

        // Чекаємо поки зміна перейде в статус OPENED
        $status = isset($response['status']) ? strtoupper((string)$response['status']) : '';
        for ($attempt = 0; $attempt < $maxWaitAttempts && $status !== 'OPENED'; $attempt++) {
            sleep($waitSeconds);
            $statusResponse = $this->checkShiftStatus($shiftId);
            if (!empty($this->errors) || !is_array($statusResponse)) {
                break;
            }
            $status = isset($statusResponse['status']) ? strtoupper((string)$statusResponse['status']) : '';
        }

        return $shiftsEntity->getActiveShift() ?: null;
    }

    /**
     * Інформація про поточного касира. Кешується в сесії між веб-запитами.
     *
     * @return array|false
     */
    public function getCashierInfo()
    {
        if (php_sapi_name() !== 'cli' && isset($_SESSION['cashier']) && is_array($_SESSION['cashier'])) {
            return $_SESSION['cashier'];
        }

        $this->clearErrors();
        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            if (!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $response = $this->makeApiRequest('cashier/me', [], ['method' => 'GET']);
        if (is_array($response) && php_sapi_name() !== 'cli') {
            $_SESSION['cashier'] = $response;
        }
        return $response;
    }

    /**
     * Зберігає або оновлює зміну в БД за shift_id.
     */
    private function saveShiftToDatabase(array $response): bool
    {
        if (empty($response['id'])) {
            return false;
        }

        $shift = new \stdClass();
        $shift->shift_id = (string)$response['id'];
        $shift->serial = isset($response['serial']) ? (int)$response['serial'] : 0;
        $shift->status = isset($response['status']) ? strtoupper((string)$response['status']) : 'ERROR';
        $shift->shift_report_id = isset($response['z_report']['id']) ? (string)$response['z_report']['id'] : '';

        if (!empty($response['opened_at']) && is_string($response['opened_at'])) {
            $timestamp = strtotime($response['opened_at']);
            $shift->opened_at = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
        } else {
            $shift->opened_at = null;
        }

        if (!empty($response['closed_at']) && is_string($response['closed_at'])) {
            $timestamp = strtotime($response['closed_at']);
            $shift->closed_at = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
        } else {
            $shift->closed_at = null;
        }

        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        $existingShift = $shiftsEntity->findOne(['shift_id' => $shift->shift_id]);
        if ($existingShift) {
            $shiftsEntity->update($existingShift->id, $shift);
        } else {
            $shiftsEntity->add($shift);
        }

        return true;
    }

    /**
     * Після виклику closeShift() поллить статус до CLOSED або вичерпання спроб.
     */
    private function waitForShiftClosed(string $shiftId, int $maxAttempts = 5, int $delaySeconds = 2): void
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($delaySeconds);

            $statusResponse = $this->checkShiftStatus($shiftId);
            if (!empty($this->errors) || !is_array($statusResponse)) {
                break;
            }

            $status = isset($statusResponse['status']) ? strtoupper((string)$statusResponse['status']) : '';
            if ($status === 'CLOSED' || ($status !== 'CLOSING' && $status !== '')) {
                break;
            }
        }
    }
}
