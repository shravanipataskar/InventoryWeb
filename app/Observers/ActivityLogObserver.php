<?php

namespace App\Observers;

use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function created(Model $model)
    {
        $this->record($model, 'Created');
    }

    public function updated(Model $model)
    {
        $this->record($model, 'Updated');
    }

    public function deleted(Model $model)
    {
        $this->record($model, 'Deleted');
    }

    private function record(Model $model, $action)
    {
        if (app()->runningInConsole()) {
            return;
        }

        $subject = class_basename($model);
        $identifier = $this->identifier($model);

        ActivityLogger::log(
            $action . ' ' . $subject,
            $subject,
            $action . ' ' . strtolower($subject) . ($identifier ? ' "' . $identifier . '"' : '') . '.'
        );
    }

    private function identifier(Model $model)
    {
        foreach (['name', 'product_code', 'transfer_number', 'reference_number', 'invoice_number'] as $attribute) {
            $value = $model->getAttribute($attribute);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $model->getKey();
    }
}
