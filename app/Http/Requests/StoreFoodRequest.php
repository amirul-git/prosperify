<?php

namespace App\Http\Requests;

use App\Models\SubCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreFoodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        return $user->hasAnyRole(['donor', 'admin', 'volunteer']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $subCategory = SubCategory::find($this->sub_category_id);

        return [
            'name' => 'required|max:100',
            'detail' => 'required|max:255',
            'amount' => 'required|max:10',
            'unit_id' => 'required|max:5',
            'expired_date' => 'required|after_or_equal:' . $this->allowedExpiredDate($this->rescue->rescue_date) . '|before:' . $this->maxAllowedExpiredDate($this->rescue->rescue_date, $subCategory->expiration_day_limit),
            'sub_category_id' => 'required|max:5',
        ];
    }

    private function allowedExpiredDate($rescueDate)
    {
        return Carbon::createFromFormat('d M Y H:i', $rescueDate)->format('m/d/y');
    }

    private function maxAllowedExpiredDate($rescueDate, int $incementor)
    {
        return Carbon::createFromFormat('d M Y H:i', $rescueDate)->addDays($incementor)->format('m/d/y');
    }
}
