<?php

namespace Laraning\NovaTimeField;

use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

class TimeField extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-time-field';

    /**
     * Create a new field.
     *
     * @param string $name
     * @param string|null $attribute
     * @param mixed|null $resolveCallback
     *
     * @return void
     */
    public function __construct($name, $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback ?? function ($value) use ($attribute) {
                if ($value === null) {
                    return null;
                }

                // Convert the value string into a Carbon date/time object.
                $value = Carbon::parse($value)->format($this->format());

                if (!$value) {
                    throw new \Exception('Field ' . ($attribute ?? '') . ' must contain a valid time value.');
                }

                return $value;
            });
    }

    /**
     * Validates if the 12h format value is a 12h format.
     *
     * @return string
     */
    public function format()
    {
        return ($this->meta['twelveHourTime'] ?? false) ? 'g:i A' : 'H:i';
    }

    /**
     * Defines the minute increment step.
     *
     * @param int $step
     *
     * @return $this
     */
    public function minuteIncrement(int $step)
    {
        return $this->withMeta(['minuteIncrement' => $step]);
    }

    /**
     * Applies 12h format in the field.
     *
     * @return $this
     */
    public function withTwelveHourTime()
    {
        return $this->withMeta(['twelveHourTime' => true]);
    }

    /**
     * @param int|null $adjustment The adjustment to be applied to the date from the database.
     *
     * @return $this
     */
    public function withTimezoneAdjustments(int $adjustment = null)
    {
        return $this->withMeta([
            'timezoneAdjustments' => true,
            'timezoneAdjustment' => $adjustment,
        ]);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param string $requestAttribute
     * @param object $model
     * @param string $attribute
     *
     * @return void
     */
    protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if ($request->exists($requestAttribute)) {
            if (empty($request->input($requestAttribute))) {
                // Call parent method from the Nova framework immediately.
                parent::fillAttributeFromRequest($request, $requestAttribute, $model, $attribute);

                return;
            }

            $value = $request->input($requestAttribute);

            // If we are having a 12 hour time format, we need to convert it to 24h time format.
            // Otherwise/default just check the time format validation.
            $validatedFormat = $this->validatedTimeFormat($value);
            $hydratedValue = Carbon::createFromFormat($validatedFormat, $value)->format('H:i:s');

            // Readjust the request attribute value, and call parent for compatibility reasons.
            $request[$requestAttribute] = $hydratedValue;

            // Call parent method from the Nova framework.
            parent::fillAttributeFromRequest($request, $requestAttribute, $model, $attribute);
        }
    }

    /**
     * Validate format of given time and return correct format.
     *
     * @param string $timeString
     *
     * @return string|bool
     */
    protected function validatedTimeFormat(string $timeString)
    {
        $allowedFormats = [
            'H:i',
            'H:i:s',
            'H:i:s.v',
            'H:i:s.u',
            $this->format(),
        ];

        foreach ($allowedFormats as $format) {
            try {
                if (Carbon::createFromFormat($format, $timeString) !== false) {
                    return $format;
                }
            } catch (\InvalidArgumentException) {
            }
        }

        return false;
    }
}
