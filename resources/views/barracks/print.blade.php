<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Barracks permit forms · Let’s Run Ibadan</title>
        <style>
            @page { size: A4; margin: 18mm; }
            * { box-sizing: border-box; }
            body { margin: 0; background: #e9e9e9; color: #111; font: 12pt/1.5 "Times New Roman", Times, serif; }
            .toolbar { display: flex; gap: 12px; align-items: center; justify-content: center; padding: 16px; font: 14px/1.4 system-ui, sans-serif; }
            .toolbar button { padding: 8px 18px; border: 2px solid #111; border-radius: 999px; background: #FEB203; font: 600 14px/1 system-ui, sans-serif; cursor: pointer; }
            .permit { position: relative; width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto 24px; padding: 18mm; background: #fff; }
            .permit-restricted { margin: 0; text-align: center; font-size: 10pt; letter-spacing: .04em; }
            .permit-passport { position: absolute; top: 18mm; right: 18mm; width: 35mm; height: 45mm; border: 1px solid #111; object-fit: cover; }
            .permit-title { margin: 22mm 0 4mm; text-align: center; font-size: 16pt; font-weight: 700; text-decoration: underline; letter-spacing: .06em; }
            .permit-form-no { margin: 0 0 8mm; text-align: right; font-size: 9pt; }
            .permit-line { display: flex; gap: 8px; margin: 0 0 4.5mm; }
            .permit-line b { flex: none; font-weight: 400; }
            .permit-line span { flex: 1; min-width: 0; border-bottom: 1px dotted #111; padding: 0 4px; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; }
            .permit-pair { display: flex; gap: 16px; }
            .permit-pair .permit-line { flex: 1; }
            .permit-heading { margin: 10mm 0 3mm; text-align: center; font-size: 12pt; font-weight: 700; text-decoration: underline; }
            .permit-declaration { margin: 0 0 12mm; text-align: justify; }
            .permit-sign { width: 60%; margin-left: auto; }
            @media print {
                body { background: none; }
                .toolbar { display: none; }
                .permit { width: auto; min-height: 0; margin: 0; padding: 0; break-after: page; }
                .permit:last-child { break-after: auto; }
                .permit-passport { top: 0; right: 0; }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <span>{{ trans_choice('{1} One form|[2,*] :count forms', max($registrations->count(), 1)) }}, one per page.</span>
            <button type="button" onclick="window.print()">Print</button>
        </div>

        @foreach ($registrations as $registration)
            <article class="permit">
                <p class="permit-restricted">RESTRICTED</p>
                <img class="permit-passport" src="{{ $registration->passportUrl() }}" alt="Passport photograph of {{ $registration->full_name }}">

                <h1 class="permit-title">PERMIT</h1>
                <p class="permit-form-no">FORM NO: ____________</p>

                <p class="permit-line"><b>Name:</b><span>{{ $registration->full_name }}</span></p>
                <div class="permit-pair">
                    <p class="permit-line"><b>State of Origin:</b><span>{{ $registration->state_of_origin }}</span></p>
                    <p class="permit-line"><b>LGA:</b><span>{{ $registration->lga }}</span></p>
                </div>
                <p class="permit-line"><b>Residential address:</b><span>{{ $registration->residential_address }}</span></p>
                <p class="permit-line"><b>Occupation:</b><span>{{ $registration->occupation }}</span></p>
                <p class="permit-line"><b>Phone No:</b><span>{{ $registration->phone }}</span></p>
                <p class="permit-line"><b>Work activity in the cantonment:</b><span>{{ $details['work_activity'] }}</span></p>
                <p class="permit-line"><b>Place of work in the cantonment:</b><span>{{ $details['place_of_work'] }}</span></p>
                <div class="permit-pair">
                    <p class="permit-line"><b>Start of work:</b><span>{{ $details['start_of_work'] }}</span></p>
                    <p class="permit-line"><b>End of work:</b><span>{{ $details['end_of_work'] }}</span></p>
                </div>

                <h2 class="permit-heading">UNDERTAKING/DECLARATION</h2>
                <p class="permit-declaration">I hereby declare that information provided above is true. I also undertake to abide all rules and regulations guiding the operation in {{ $details['cantonment'] }}.</p>

                <div class="permit-sign">
                    <p class="permit-line"><b>Signature:</b><span>{{ $registration->signature_name }}</span></p>
                    <p class="permit-line"><b>Date:</b><span>{{ $registration->declared_at->format('j F Y') }}</span></p>
                </div>
            </article>
        @endforeach
    </body>
</html>
