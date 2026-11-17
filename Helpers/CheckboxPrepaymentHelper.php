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
    private const int HTTP_NOT_FOUND = 404;

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
     * null означає «не вдалось дізнатись» — таймаут, 5xx, 403 тощо.
     * Викликач НЕ має права трактувати його як «ланцюжка немає»: воно веде до
     * ACTION_SALE і другого чека поверх сплаченого авансу. Для цього є
     * CheckboxChainDecision::STATUS_UNKNOWN.
     *
     * 404 — окремо: Checkbox відповів, що такого ланцюжка не знає («Prepayment
     * receipt relation not found»), і повтор цього не змінить. Тоді стан —
     * CheckboxChainDecision::STATUS_NOT_FOUND, без запису ERROR у лог.
     *
     * not_found — лише 404 саме на цей GET і з бізнес-полем message від Checkbox.
     * 404 на вході касира (cashier/signin) чи загальний {"detail": "Not Found"}
     * на невідомий маршрут про ланцюжок нічого не кажуть і лишаються null. Текст
     * повідомлення не порівнюємо: він може змінитись. HTML-сторінка 404 від проксі
     * http_code не ставить взагалі: processApiResponse() віддає її рядком.
     */
    public function chainStatus(string $relationId): ?array
    {
        $url = 'prepayment-receipts/' . rawurlencode($relationId);
        $response = $this->authorisedRequest($url, [], 'GET', 'chain status unavailable', [self::HTTP_NOT_FOUND]);

        if ($this->answeredNotFound($url)) {
            $message = $this->errors['response']['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return ['pre_payment_status' => CheckboxChainDecision::STATUS_NOT_FOUND];
            }
            // authorisedRequest() 404 пропустив без логу, а це звичайний збій.
            $this->logFailure('chain status unavailable', ['url' => $url]);
        }

        return is_array($response) && isset($response['pre_payment_status']) ? $response : null;
    }

    /** 404 саме на запит $url, а не на вхід касира перед ним. */
    private function answeredNotFound(string $url): bool
    {
        $requestUrl = $this->errors['requestData']['url'] ?? null;

        return (int)($this->errors['http_code'] ?? 0) === self::HTTP_NOT_FOUND
            && is_string($requestUrl)
            && str_ends_with($requestUrl, '/' . $url);
    }

    /**
     * @param array|null $delivery куди надіслати чеки повернення, {@see CheckboxReceiptDelivery}
     * @return array|string|false
     */
    public function returnChain(string $relationId, string $cashierName, ?array $delivery = null)
    {
        $payload = ['cashier_name' => $cashierName];
        if ($delivery !== null) {
            $payload['delivery'] = $delivery;
        }

        return $this->authorisedRequest(
            'prepayment-receipts/' . rawurlencode($relationId) . '/return',
            $payload,
            'POST',
            'chain not returned'
        );
    }

    /**
     * @param string $failureEvent подія для логу — крон нікому не звітує, і без
     *                            запису невиставлений чек не лишає сліду ніде
     * @param int[] $expectedHttpCodes відповіді, які викликач обробляє сам і які не є збоєм
     * @return array|string|false
     */
    private function authorisedRequest(string $url, array $params, string $method, string $failureEvent, array $expectedHttpCodes = [])
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
        if (!empty($this->errors) && !in_array((int)($this->errors['http_code'] ?? 0), $expectedHttpCodes, true)) {
            $this->logFailure($failureEvent, ['url' => $url]);
        }

        return $response;
    }
}
