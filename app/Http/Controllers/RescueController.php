<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRescueRequest;
use App\Models\Food;
use App\Models\FoodDiff;
use App\Models\FoodRescue;
use App\Models\FoodRescueLog;
use App\Models\FoodRescuePoint;
use App\Models\FoodRescueStoredReceipt;
use App\Models\FoodRescueTakenReceipt;
use App\Models\FoodRescueUser;
use App\Models\FoodVault;
use App\Models\Point;
use App\Models\PointRescueUser;
use App\Models\Rescue;
use App\Models\RescueAssignment;
use App\Models\RescueLog;
use App\Models\RescueSchedule;
use App\Models\RescueUser;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpParser\Node\Stmt\TryCatch;

class RescueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        $donor = $user->hasRole(User::DONOR);
        $manager = $user->hasRole(User::ADMIN);
        $volunteer = $user->hasRole(User::VOLUNTEER);

        if ($donor) {
            $userID = auth()->user()->id;
            $rescues = User::find($userID)->rescues;
            $filtered = $this->filterRescueByStatus($rescues, [Rescue::PLANNED]);
            $filtered = $filtered->sortBy('priority_rescue_date');

            if ($request->query('q')) {
                $filtered = $this->filterSearch($filtered, $request->query('q'));
            }

            if ($request->query('urgent')) {
                $filtered = $this->filterPriority(request()->query('urgent'), $filtered);
            }

            return view('rescues.index', ['rescues' => $filtered]);
        } else if ($manager) {
            $rescues = Rescue::all();
            $filtered = $this->filterRescueByStatus($rescues, [Rescue::SUBMITTED]);
            $filtered = $filtered->sortBy('priority_rescue_date');

            if ($request->query('q')) {
                $filtered = $this->filterSearch($filtered, $request->query('q'));
            }

            if ($request->query('urgent')) {
                $filtered = $this->filterPriority(request()->query('urgent'), $filtered);
            }

            return view('manager.rescues.index', ['rescues' => $filtered]);
        } else if ($volunteer) {
            $rescues = Rescue::all();

            $filtered = $this->filterRescueByStatus($rescues, [Rescue::ASSIGNED, Rescue::INCOMPLETED]);

            $filtered = $filtered->sortBy('priority_rescue_date');

            if ($request->query('q')) {
                $filtered = $this->filterSearch($filtered, $request->query('q'));
            }

            if ($request->query('urgent')) {
                $filtered = $this->filterPriority(request()->query('urgent'), $filtered);
            }

            $rescues = collect([]);

            foreach ($filtered as $rescue) {
                foreach ($rescue->foods as $food) {
                    $foodHasAssignment = $food->foodAssignments->count() > 0;
                    $foodIsAssignedToLogedVolunteer = $foodHasAssignment && $food->foodAssignments->last()->volunteer_id === $user->id;

                    if ($foodIsAssignedToLogedVolunteer) {
                        $rescues->push($rescue);
                        break;
                    }
                }
            }

            return view('manager.rescues.index', ['rescues' => $rescues]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        if (!$user->hasRole('donor')) {
            abort(403);
        }

        $prep = Setting::first()->rescue_preptime;

        $user = auth()->user();
        return view('rescues.create', ['user' => $user, 'prep' => $prep]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRescueRequest $request)
    {
        // validate if there's an active donation
        $rescues = Rescue::where('rescue_status_id', Rescue::PLANNED)
            ->orWhere('rescue_status_id', Rescue::SUBMITTED)
            ->orWhere('rescue_status_id', Rescue::PROCESSED)
            ->orWhere('rescue_status_id', Rescue::ASSIGNED)
            ->orWhere('rescue_status_id', Rescue::INCOMPLETED)->get();

        foreach ($rescues as $rescue) {
            $dbRescueDate = Carbon::parse($rescue->rescue_date);
            // rescue time is 4 hour
            $rescueDuration = Setting::first()->rescue_duration;
            $dbEndRescueDate = Carbon::parse($rescue->rescue_date)->addMinutes($rescueDuration);
            $reqRescueDate = Carbon::parse($request->rescue_date);

            $conflictDonation = $reqRescueDate->between($dbRescueDate, $dbEndRescueDate);

            if ($conflictDonation) {
                return redirect(route('rescues.create'))->with('conflict', "Conflicting with $rescue->title, start: $dbRescueDate, finish: $dbEndRescueDate. Try another date time");
            }
        }

        $validated = $request->validated();
        $user = auth()->user();

        try {
            DB::beginTransaction();
            $attributes = $request->only(['donor_name', 'pickup_address', 'phone', 'email', 'title', 'description', 'rescue_date']);
            $rescue = new Rescue();
            $rescue->fill($attributes);
            $rescue->user_id = auth()->user()->id;
            $rescue->rescue_status_id = Rescue::PLANNED;
            $rescue->save();

            RescueLog::Create($user, $rescue);

            DB::commit();
            return redirect()->route('rescues.show', ['rescue' => $rescue]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Rescue $rescue)
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        $donor = $user->hasRole(User::DONOR);
        $manager = $user->hasAnyRole(User::VOLUNTEER, User::ADMIN);

        if ($donor) {
            return view('rescues.show', ['rescue' => $rescue->load('foods')]);
        } else if ($manager) {
            $volunteers = [];
            $vaults = [];
            $rescueProcessed = $rescue->rescue_status_id === Rescue::PROCESSED;
            if ($rescueProcessed) {
                $volunteers = User::role(User::VOLUNTEER)->get();
                $vaults = Vault::all();
            }
            return view('manager.rescues.show', ['rescue' => $rescue, 'volunteers' => $volunteers, 'vaults' => $vaults]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Rescue $rescue)
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        if (!$user->hasAnyRole(['donor', 'admin'])) {
            abort(403);
        }

        if ($user->hasRole('donor') && $rescue->rescue_status_id === Rescue::SUBMITTED) {
            abort(403);
        }

        return view('rescues.edit', ['user' => $user, 'rescue' => $rescue]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rescue $rescue)
    {
        $user = auth()->user();
        try {
            DB::beginTransaction();

            $attributes = $request->only(['title', 'description', 'pickup_address', 'rescue_date', 'donor_name', 'phone', 'email']);
            $rescue->fill($attributes);
            $rescue->save();

            RescueLog::Create($user, $rescue);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route("rescues.show", ['rescue' => $rescue]);
    }

    public function updateStatus(Request $request, Rescue $rescue)
    {
        $user = auth()->user();
        $isPhotoInRequest = count($request->file());
        try {

            DB::beginTransaction();

            $rescue->rescue_status_id = (int)$request->status;
            $rescue->save();
            RescueLog::Create($user, $rescue);

            $rescueSubmitted = $rescue->rescue_status_id === Rescue::SUBMITTED;
            $rescueRejected = $rescue->rescue_status_id === Rescue::REJECTED;
            $rescueProcessed = $rescue->rescue_status_id === Rescue::PROCESSED;
            $rescueAssigned = $rescue->rescue_status_id === Rescue::ASSIGNED;
            $rescueIncompleted = $rescue->rescue_status_id === Rescue::INCOMPLETED;

            if ($rescueSubmitted) {
                $this->updateFoodsStatus($user, $rescue, Food::SUBMITTED, null);
            } else if ($rescueRejected) {
                $this->updateFoodsStatus($user, $rescue, Food::REJECTED, null);
            } else if ($rescueProcessed) {
                $this->updateFoodsStatus($user, $rescue, Food::PROCESSED, null);
            } else if ($rescueAssigned) {
                $this->updateFoodsStatus($user, $rescue, Food::ASSIGNED, null);
            } else if ($rescueIncompleted) {
                if ($isPhotoInRequest) {
                    $foodId = $this->getFoodId($request);
                    $vaultId = $this->getVaultID($request, $foodId);
                    $food = Food::find($foodId);
                    $vault = Vault::find($vaultId);
                    $photo = $this->storePhoto($request, $foodId);

                    $this->updateFoodStatus($request, $rescue, $food, $user, $vault, $photo);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            throw $e;
            DB::rollBack();
        }

        return redirect()->route('rescues.show', ['rescue' => $rescue]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rescue $rescue)
    {
        $rescue->delete();
        return redirect()->route('rescues.index');
    }

    public function history(Rescue $rescue, Food $food)
    {
        $foodRescueLogs = FoodRescueLog::where(['food_id' => $food->id, 'rescue_id' => $rescue->id])->get();
        return view('history.index', ['foodRescueLogs' => $foodRescueLogs]);
    }

    private function getFoodId(Request $request)
    {
        $foodKey = null;
        foreach ($request->file() as $key => $value) {
            $foodKey = $key;
        }
        $foodId = ((int)explode("-", $foodKey)[0]);
        return $foodId;
    }

    // batch update for food before assigned
    private function updateFoodsStatus($user, $rescue, $status, $vault)
    {
        foreach ($rescue->foods as $food) {
            $foodIsNotRejectedNorCanceled = !in_array($food->food_rescue_status_id, [Food::REJECTED, Food::CANCELED]);
            if ($foodIsNotRejectedNorCanceled) {
                $food->food_rescue_status_id = $status;
                $food->save();
                $foodRescueLog = FoodRescueLog::Create($user, $rescue, $food, $vault);

                // assignment dan schedule disini
                if ($rescue->rescue_status_id === Rescue::ASSIGNED) {
                    $vaultID = $this->getVaultID(request(), $food->id);
                    $volunteerID = $this->getVolunteerID(request(), $food->id);

                    RescueAssignment::Create($food, $rescue, $user, $volunteerID, $vaultID);
                    RescueSchedule::Create($rescue, $food, $volunteerID);
                }
            }
        }
    }

    // individual update for food after assigned
    private function updateFoodStatus($request, $rescue, $food, $user, $vault, $photo,)
    {
        $foodAssigned = $food->food_rescue_status_id === Food::ASSIGNED;
        $foodTaken = $food->food_rescue_status_id === Food::TAKEN;

        if ($foodAssigned) {
            $food->food_rescue_status_id = Food::TAKEN;
            $food->photo = $photo;
            // jika size nya lebih kecil dari seharusnya simpan ke rescue diff logs
            $amount = (float)$this->getAmount($request, $food->id);

            if ($amount !== $food->amount) {
                $diffAmount = $food->amount - $amount;
                FoodDiff::Create($food, $diffAmount, $user);
            }

            $food->amount = $amount;
            $food->save();

            // generate signature photo and path
            $signature = $this->getfoodSignature($request, $food->id);
            $signaturePhoto = $this->storeSignature($signature, 'rescue-donor-signature');

            FoodRescueLog::Create($user, $rescue, $food, $vault);
            $rescueAssignment = RescueAssignment::where(['food_id' => $food->id, 'rescue_id' => $rescue->id])->get()->last();
            FoodRescueTakenReceipt::Create($food, $rescueAssignment, $signaturePhoto);
        } else if ($foodTaken) {
            $food->food_rescue_status_id = Food::STORED;
            $food->photo = $photo;
            $food->stored_at = Carbon::now();
            $food->vault_id = $vault->id;

            // jika size nya lebih kecil dari seharusnya simpan ke rescue diff logs
            $amount = (float)$this->getAmount($request, $food->id);

            if ($amount !== $food->amount) {
                $diffAmount = $food->amount - $amount;
                FoodDiff::Create($food, $diffAmount, $user);
            }

            $food->amount = $amount;
            $food->save();

            // generate signature photo and path
            $signature = $this->getfoodSignature($request, $food->id);
            $signaturePhoto = $this->storeSignature($signature, 'rescue-admin-signature');

            FoodRescueLog::Create($user, $rescue, $food, $vault);
            $rescueAssignment = RescueAssignment::where(['food_id' => $food->id, 'rescue_id' => $rescue->id])->get()->last();
            $foodRescueStoredReceipt =  FoodRescueStoredReceipt::Create($food, $rescueAssignment, $signaturePhoto);

            // delete rescue schedule
            $rescueSchedule = RescueSchedule::where('food_id', $food->id)->first();
            $rescueSchedule->delete();

            // update user point
            $userPoint = Point::where('user_id', $rescue->user_id)->first();
            $userPoint->point = (int)$userPoint->point + (int)$food->amount;
            $userPoint->save();

            // save log
            $foodRescuePoint = new FoodRescuePoint();
            $foodRescuePoint->point_id = $userPoint->id;
            $foodRescuePoint->food_rescue_stored_receipt_id = $foodRescueStoredReceipt->id;
            $foodRescuePoint->point = $food->amount;
            $foodRescuePoint->save();

            $this->changeRescueToComplete($rescue, $user);
        }
    }

    private function changeRescueToComplete($rescue, $user)
    {
        // check apakah semua food yang tidak reject dan tidak canceled sudah stored, if yes maka ubah rescue status ke complete

        $allfoodStored = true;

        foreach ($rescue->foods as $food) {
            $foodNotRejectedNorCanceled = !in_array($food->food_rescue_status_id, [Food::REJECTED, Food::CANCELED]);
            $foodHasNotBeenStored = !in_array($food->food_rescue_status_id, [Food::STORED, Food::ADJUSTED_AFTER_STORED]);
            if ($foodNotRejectedNorCanceled && $foodHasNotBeenStored) {
                $allfoodStored = false;
            }
        }

        if ($allfoodStored) {
            $rescue->rescue_status_id = Rescue::COMPLETED;
            $rescue->save();
            RescueLog::Create($user, $rescue);
        }
    }

    private function filterRescueByStatus($rescues, $arrRescueStatusID)
    {
        $filtered = $rescues->filter(function ($rescues) use ($arrRescueStatusID) {
            $status = request()->query('status');
            if ($status === null) {
                return in_array($rescues->rescue_status_id, $arrRescueStatusID);
            }
            return $rescues->rescue_status_id === (int) request()->query('status');
        });

        return $filtered;
    }

    private function storePhoto($request, $foodID)
    {
        $photoURL = $request->file("$foodID-photo")->store('rescue-documentations');
        return $photoURL;
    }

    private function getVolunteerID($request, $foodID)
    {
        return $request["food-$foodID-volunteer_id"];
    }

    private function getVaultID($request, $foodID)
    {
        return $request["food-$foodID-vault_id"];
    }

    private function getAmount($request, $foodID)
    {
        return $request["food-$foodID-amount"];
    }

    private function getfoodSignature($request, $foodID)
    {
        return $request["food-$foodID-signature"];
    }

    private function storeSignature($signature, $dir)
    {
        $data_uri = $signature;
        $encoded_image = explode(",", $data_uri)[1];
        $decoded_image = base64_decode($encoded_image);

        $extension = "jpeg";
        $imageName = $dir . '/' . Str::random(10) . '.' . $extension;

        Storage::disk('public')->put($imageName, $decoded_image);

        return $imageName;
    }

    private function filterSearch($collections, $filterValue)
    {
        $filtered = $collections->filter(function ($f) use ($filterValue) {
            return str_contains(strtolower($f->title), strtolower($filterValue));
        });

        return $filtered;
    }

    private function filterPriority($urgent, $collections)
    {
        $filtered = $collections;
        if ($urgent === 'on') {
            $filtered = $collections->sortBy('rescue_date');
        } else {
            return $collections;
        }
        return $filtered;
    }
}
