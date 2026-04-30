<div class="rounded-3xl border bg-white p-6">
    <h2 class="text-xl font-bold text-slate-900">Stripe Connect prestataires</h2>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-slate-500">
                    <th class="py-2">Employé</th>
                    <th>Status</th>
                    <th>Compte Stripe</th>
                    <th>Charges</th>
                    <th>Payouts</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $employee)
                    <tr class="border-b">
                        <td class="py-3 font-semibold">{{ $employee->name }}</td>
                        <td>{{ $employee->stripe_connect_status }}</td>
                        <td>{{ $employee->stripe_connect_account_id ?? '—' }}</td>
                        <td>{{ $employee->stripe_connect_charges_enabled_at ? 'OK' : 'Non' }}</td>
                        <td>{{ $employee->stripe_connect_payouts_enabled_at ? 'OK' : 'Non' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>