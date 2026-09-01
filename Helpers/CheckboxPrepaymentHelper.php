<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Виклики ланцюжка передплати та післяплати.
 *
 * Винесено окремо від CheckboxReceiptsHelper: той уже відповідає за звичайні
 * чеки, крон і збереження в базу, і третій сценарій зробив би його неоглядним.
 */
class CheckboxPrepaymentHelper extends CheckboxApiHelper
{
    /**
     * Прив'язує $accessToken і $errors за посиланням до іншого хелпера.
     * @see CheckboxShiftsHelper::syncWithHelper()
     */
    public function syncWithHelper(CheckboxApiHelper $helper): void
    {
        $this->accessToken = &$helper->accessToken;
        $this->errors = &$helper->errors;
    }

    /** @return array|string|false */
    public function createChain(array $payload)
    {
        return $this->authorisedRequest('prepayment-receipts', $payload, 'POST', 'prepayment chain not created');
    }

    /** @return array|string|false */
    public function addPayment(string $relationId, array $payload)
    {
        return $this->authorisedRequest(
            'prepayment-receipts/' . rawurlencode($relationId),
            $payload,
            'POST',
            'chain payment not fiscalised'
        );
    }

    /**
     * Стан ланцюжка. Єдине джерело істини: і чек передплати, і чек післяплати
     * повертаються з однаковим type SELL, тож з відповіді на POST неможливо
     * зрозуміти, чи ланцюжок закрився.
     *
     * null означає «не вдалось дізнатись» — таймаут, 5xx, 403 і 404 нерозрізнимі.
     * Викликач НЕ має права трактувати його як «ланцюжка немає»: воно веде до
     * ACTION_SALE і другого чека поверх сплаченого авансу. Для цього є
     * CheckboxChainDecision::STATUS_UNKNOWN.
     */
    public function chainStatus(string $relationId): ?array
    {
        $response = $this->authorisedRequest(
            'prepayment-receipts/' . rawurlencode($relationId),
            [],
            'GET',
            'chain status unavailable'
        );

        return is_array($response) && isset($response['pre_payment_status']) ? $response : null;
    }

    /** @return array|string|false */
    public function returnChain(string $relationId, string $cashierName)
    {
        return $this->authorisedRequest(
            'prepayment-receipts/' . rawurlencode($relationId) . '/return',
            ['cashier_name' => $cashierName],
            'POST',
            'chain not returned'
        );
    }

    /**
     * @param string $failureEvent подія для логу — крон нікому не звітує, і без
     *                            запису невиставлений чек не лишає сліду ніде
     * @return array|string|false
     */
    private function authorisedRequest(string $url, array $params, string $method, string $failureEvent)
    {
        $this->clearErrors();

        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            // Перевірка саме на токен: при незаповнених налаштуваннях касира
            // getAccessToken() повертає повідомлення, не чіпаючи errors, — і без
            // цієї умови сюди йшов би дарма кинутий запит у бойовий API.
            if (!empty($this->errors) || empty($this->accessToken)) {
                return $tokenResponse;
            }
        }

        $response = $this->makeApiRequest($url, $params, ['method' => $method]);
        if (!empty($this->errors)) {
            $this->logFailure($failureEvent, ['url' => $url]);
        }

        return $response;
    }
}
