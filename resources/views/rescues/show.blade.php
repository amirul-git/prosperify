@extends('layouts.index')

@section('main')
    <main class="p-6 text-slate-900">
        <div>
            <div>
                <div class="flex items-center justify-between">
                    @php
                        $rescueStatus = $rescue->rescue_status_id;
                        $rescuePlanned = $rescueStatus === 1;
                    @endphp
                    <a class="flex items-center gap-2"
                        @if ($rescuePlanned) href="{{ route('rescues.edit', ['rescue' => $rescue]) }}" @endif>
                        <h1 class="text-2xl font-bold">{{ $rescue->title }}</h1>
                        @if ($rescuePlanned)
                            <x-heroicon-o-pencil-square class="w-[18px] h-[18px]" />
                        @endif
                    </a>
                    @if ($rescuePlanned)
                        <form onclick="return confirm('Are you sure?')"
                            action="{{ route('rescues.destroy', ['rescue' => $rescue]) }}" method="post">
                            @csrf
                            @method('delete')
                            <button class="w-8 h-8 flex items-center justify-center">
                                <x-heroicon-o-trash class="w-[18px] h-[18px] text-red-600" />
                            </button>
                        </form>
                    @endif
                </div>
                <div class="mt-3 flex items-center gap-1 text-slate-500">
                    <x-heroicon-o-calendar class="w-[18px] h-[18px]" />
                    <p class="text-sm">Created at {{ $rescue->created_at }}
                </div>
                <div class="mt-3 flex items-center gap-1 text-slate-500 capitalize">
                    @include('rescues.partials.status-icon')
                    <p class="text-sm"> {{ $rescue->rescueStatus->name }}
                </div>
            </div>
        </div>
        <form action="{{ route('rescues.update.status', ['rescue' => $rescue]) }}" method="post"
            enctype="multipart/form-data">
            @method('put')
            @csrf
            <div class="mt-3">
                <section class="flex items-center gap-2">
                    <div class="w-11 h-11 bg-[#F4F6FA] rounded-md flex items-center justify-center">
                        <x-heroicon-o-user class="w-6 h-6" />
                    </div>
                    <div>
                        <p>{{ $rescue->donor_name }}</p>
                        <p class="text-xs text-slate-500 capitalize">Donor</p>
                    </div>
                </section>
                <section class="mt-6">
                    <h2 class="text-lg font-bold">Rescue Date</h2>
                    <div class="mt-2 flex items-center gap-1">
                        <x-heroicon-o-calendar class="w-[18px] h-[18px]" />
                        <p class="text-sm">{{ $rescue->rescue_date }}
                    </div>
                    <div class="mt-2 flex items-center gap-1">
                        <x-heroicon-o-map-pin class="w-[18px] h-[18px]" />
                        <p class="text-sm">{{ $rescue->pickup_address }}
                    </div>
                </section>
                <section>
                    <input type="text" value="2" name="status" hidden>
                    @if (!$rescue->foods->isEmpty() && $rescue->rescue_status_id === 1)
                        <Button
                            class="py-2 w-full rounded-md bg-slate-900 mt-8 text-sm font-medium text-white">Submit</Button>
                    @endif
                </section>
            </div>
            <div class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">Foods</h2>
                    @if ($rescue->rescue_status_id === 1)
                        <a href="{{ route('rescues.foods.create', ['rescue' => $rescue]) }}"
                            class="flex items-center gap-1">
                            <x-heroicon-o-plus class="w-5 h-5" />Add
                        </a>
                    @endif
                </div>
                <div>
                    @if ($rescue->foods->isEmpty())
                        <p class="mt-6 font-medium text-center">There's no food here yet</p>
                        <div class="flex justify-center mt-3">
                            <a href="{{ route('rescues.foods.create', ['rescue' => $rescue]) }}"
                                class="py-2 px-4 rounded-md bg-slate-900 text-white font-medium text-sm">Add a new food</a>
                        </div>
                    @else
                        <div class="mt-4">
                            @foreach ($rescue->foods as $food)
                                @php
                                    $foodRescueStatus = $food->pivot->foodRescueStatus;
                                    $foodRescueNotCanceled = $foodRescueStatus->name !== 'canceled';
                                    $user = $food->pivot->user;
                                @endphp
                                @if ($foodRescueNotCanceled)
                                    <a href="{{ route('rescues.foods.show', ['rescue' => $rescue, 'food' => $food]) }}">
                                        <section class="p-6 border border-slate-200 rounded-md mb-4">
                                            <div class="flex items-center gap-4">
                                                <div>
                                                    <img class="w-[72px] h-[72px] rounded-md object-cover"
                                                        src="{{ asset("storage/$food->photo") }}" alt="">
                                                </div>
                                                <div>
                                                    <h3 class="text-2xl font-bold">
                                                        {{ $food->amount }}.<span
                                                            class="text-base">{{ $food->unit->name }}</span>
                                                    </h3>
                                                    <p class="text-slate-500">{{ $food->name }}</p>
                                                    <p class="text-xs text-slate-500">
                                                        Exp. {{ $food->expired_date }}
                                                    </p>
                                                </div>
                                            </div>
                                            <section class="flex items-center gap-2 mt-4">
                                                <div
                                                    class="w-11 h-11 bg-[#F4F6FA] rounded-md flex items-center justify-center">
                                                    @include('foods.partials.food-status')
                                                </div>
                                                <div>
                                                    <p>
                                                        <span class="capitalize">{{ $foodRescueStatus->name }}</span>
                                                        by
                                                        {{ $user->name }}
                                                    </p>
                                                    <p class="text-xs text-slate-500 capitalize">
                                                        At
                                                        {{ $food->pivot->updated_at }}
                                                    </p>
                                                </div>
                                            </section>
                                            <section>
                                                @if ($rescue->rescue_status_id > 3)
                                                    <section class="flex justify-between">
                                                        <div>
                                                            <p class="mt-4 text-sm font-medium">Volunteer
                                                            </p>
                                                            <div class="mt-2">
                                                                <select class="rounded-md border border-slate-300"
                                                                    name="food-{{ $food->id }}-volunteer_id"
                                                                    id="volunteer" disabled>
                                                                    <option>
                                                                        {{ $food->pivot->volunteer->name }}
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <p class="mt-4 text-sm font-medium">Vaults
                                                            </p>
                                                            <div class="mt-2">
                                                                <select class="rounded-md border border-slate-300"
                                                                    name="food-{{ $food->id }}-vault_id" id="vault"
                                                                    disabled>
                                                                    <option value="">
                                                                        {{ $food->pivot->vault->name }}
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </section>
                                                @endif
                                            </section>
                                            @if ($foodRescueStatus->id < 2)
                                                <section class="mt-4">
                                                    <p>On <span>
                                                            @include('foods.partials.food-submit-photo-status')
                                                        </span> condition</p>
                                                    <input class="mt-2 w-full border border-slate-200 rounded-md p-2"
                                                        type="file" name="{{ $food->id }}-photo" required>
                                                </section>
                                            @endif
                                        </section>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </form>
    </main>
@endsection
