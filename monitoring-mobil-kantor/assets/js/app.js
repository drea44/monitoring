function openModal(id){document.getElementById(id)?.classList.add('show')}
function closeModal(id){document.getElementById(id)?.classList.remove('show')}

document.addEventListener('click', function(e){
    if(e.target.classList.contains('modal-backdrop')) e.target.classList.remove('show')
})

async function openBookingModal(id){
    const body = document.getElementById('bookingModalBody')
    body.innerHTML = '<p class="text-muted">Memuat detail...</p>'
    openModal('bookingModal')
    try{
        const res = await fetch('api/booking_json.php?id=' + encodeURIComponent(id))
        const data = await res.json()
        if(!data.ok){ body.innerHTML = '<p>Data tidak ditemukan.</p>'; return }
        const b = data.booking
        body.innerHTML = `
            <div class="detail-grid">
                <div class="detail-item"><span>Kode</span><strong>${b.code}</strong></div>
                <div class="detail-item"><span>Status</span><strong>${b.status_label}</strong></div>
                <div class="detail-item"><span>Tanggal Berangkat</span><strong>${b.date_label}</strong></div>
                <div class="detail-item"><span>Tanggal Pulang</span><strong>${b.return_date_label}</strong></div>
                <div class="detail-item"><span>Jam</span><strong>${b.start_time} - ${b.end_time}</strong></div>
                <div class="detail-item"><span>Mobil</span><strong>${b.car_name || '-'}</strong><small>${b.plate_number || ''}</small></div>
                <div class="detail-item"><span>Driver</span><strong>${b.driver_name || '-'}</strong></div>
                <div class="detail-item"><span>Pemesan</span><strong>${b.requester}</strong></div>
                <div class="detail-item"><span>Jumlah Penumpang</span><strong>${b.passenger_count}</strong></div>
                <div class="detail-item"><span>Tujuan</span><strong>${b.destination}</strong></div>
                <div class="detail-item"><span>KM Berangkat</span><strong>${b.km_start ?? '-'}</strong></div>
                <div class="detail-item"><span>KM Kembali</span><strong>${b.km_end ?? '-'}</strong></div>
                <div class="detail-item"><span>Total KM</span><strong>${b.total_km}</strong></div>
                <div class="detail-item"><span>Uang Jalan Driver</span><strong>${b.allowance_label}</strong></div>
                <div class="detail-item"><span>Total Nota</span><strong>${b.trip_expense_label}</strong></div>
                <div class="detail-item"><span>BBM</span><strong>${b.fuel_label}</strong></div>
                <div class="detail-item"><span>Tol + Parkir + Lainnya</span><strong>${b.other_total_label}</strong></div>
            </div>
            <h3 style="margin-top:18px">Penumpang</h3>
            <p>${data.passengers.length ? data.passengers.map(p=>p.name).join(', ') : '-'}</p>
            <div class="actions" style="margin-top:18px">
                <a class="btn btn-primary" href="booking_detail.php?id=${b.id}">Buka Detail Lengkap</a>
                <a class="btn btn-outline" href="expense_upload.php?booking_id=${b.id}">Upload Nota</a>
            </div>
        `
    }catch(err){
        body.innerHTML = '<p>Gagal memuat detail.</p>'
    }
}

function addPassenger(){
    const wrap = document.getElementById('passengerList')
    if(!wrap) return
    const input = document.createElement('input')
    input.className = 'input'
    input.name = 'passengers[]'
    input.placeholder = 'Nama penumpang'
    wrap.appendChild(input)
}

function bookingDateTimeIsValid(date, returnDate, start, end){
    if(!date || !returnDate || !start || !end) return false
    return new Date(`${returnDate}T${end}`) > new Date(`${date}T${start}`)
}

async function checkAvailability(){
    const date = document.querySelector('[name="date"]')?.value
    const returnDate = document.querySelector('[name="return_date"]')?.value || date
    const start = document.querySelector('[name="start_time"]')?.value
    const end = document.querySelector('[name="end_time"]')?.value
    const result = document.getElementById('availabilityResult')
    if(!date || !returnDate || !start || !end){
        result.innerHTML = '<div class="alert alert-warning">Isi tanggal berangkat, tanggal pulang, jam berangkat, dan jam kembali terlebih dahulu.</div>'
        return
    }
    if(!bookingDateTimeIsValid(date, returnDate, start, end)){
        result.innerHTML = '<div class="alert alert-warning">Tanggal/jam pulang harus lebih besar dari tanggal/jam berangkat.</div>'
        return
    }
    result.innerHTML = '<p class="text-muted">Mengecek ketersediaan...</p>'
    const res = await fetch(`api/check_availability.php?date=${encodeURIComponent(date)}&return_date=${encodeURIComponent(returnDate)}&start_time=${encodeURIComponent(start)}&end_time=${encodeURIComponent(end)}`)
    const data = await res.json()
    if(data.error){
        result.innerHTML = `<div class="alert alert-danger">${data.error}</div>`
        return
    }
    let cars = data.cars.map(c=>`<label class="choice-card"><input type="radio" name="car_id" value="${c.id}"> <strong>${c.name}</strong><br><span class="text-muted">${c.plate_number} · Kapasitas ${c.capacity} · KM ${c.last_km}</span></label>`).join('') || '<p>Tidak ada mobil ready.</p>'
    let drivers = data.drivers.map(d=>`<label class="choice-card"><input type="radio" name="driver_id" value="${d.id}"> <strong>${d.name}</strong><br><span class="text-muted">${d.phone || '-'} · ${d.status}</span></label>`).join('') || '<p>Tidak ada driver ready.</p>'
    result.innerHTML = `<div class="availability-list"><div><h3>Mobil Ready</h3>${cars}</div><div><h3>Driver Ready</h3>${drivers}</div></div>`
}

function previewUpload(input){
    const preview = document.getElementById('uploadPreview')
    if(!preview || !input.files.length) return
    const file = input.files[0]
    preview.innerHTML = `<div class="preview-box"><strong>${file.name}</strong><br><span class="text-muted">${Math.round(file.size/1024)} KB · File siap diupload</span></div>`
}

async function updateRealTimeStats() {
    try {
        const res = await fetch('api/live_stats.php')
        const data = await res.json()
        if (!data.ok || !data.stats) return
        const s = data.stats

        const elToday = document.getElementById('statToday')
        if (elToday) elToday.innerText = s.today_count
        const elPending = document.getElementById('statPending')
        if (elPending) elPending.innerText = s.pending_count
        const elCarsAvailable = document.getElementById('statCarsAvailable')
        if (elCarsAvailable) elCarsAvailable.innerText = s.cars_available
        const elCarsUsed = document.getElementById('statCarsUsed')
        if (elCarsUsed) elCarsUsed.innerText = s.running_count
        const elDriversAvailable = document.getElementById('statDriversAvailable')
        if (elDriversAvailable) elDriversAvailable.innerText = s.drivers_available

        const wCarsAvail = document.getElementById('widgetCarsAvailable')
        if (wCarsAvail) wCarsAvail.innerText = s.cars_available + ' unit'
        const barCarsAvail = document.getElementById('barCarsAvailable')
        if (barCarsAvail && s.cars_total > 0) barCarsAvail.style.width = Math.round((s.cars_available / s.cars_total) * 100) + '%'

        const wCarsUsed = document.getElementById('widgetCarsUsed')
        if (wCarsUsed) wCarsUsed.innerText = s.cars_used + ' unit'
        const barCarsUsed = document.getElementById('barCarsUsed')
        if (barCarsUsed && s.cars_total > 0) barCarsUsed.style.width = Math.round((s.cars_used / s.cars_total) * 100) + '%'

        const wCarsMaint = document.getElementById('widgetCarsMaint')
        if (wCarsMaint) wCarsMaint.innerText = s.cars_maintenance + ' unit'
        const barCarsMaint = document.getElementById('barCarsMaint')
        if (barCarsMaint && s.cars_total > 0) barCarsMaint.style.width = Math.round((s.cars_maintenance / s.cars_total) * 100) + '%'

        const wDriversAvail = document.getElementById('widgetDriversAvailable')
        if (wDriversAvail) wDriversAvail.innerText = s.drivers_available + ' org'
        const barDriversAvail = document.getElementById('barDriversAvailable')
        if (barDriversAvail && s.drivers_total > 0) barDriversAvail.style.width = Math.round((s.drivers_available / s.drivers_total) * 100) + '%'

        const wDriversAssigned = document.getElementById('widgetDriversAssigned')
        if (wDriversAssigned) wDriversAssigned.innerText = s.drivers_assigned + ' org'
        const barDriversAssigned = document.getElementById('barDriversAssigned')
        if (barDriversAssigned && s.drivers_total > 0) barDriversAssigned.style.width = Math.round((s.drivers_assigned / s.drivers_total) * 100) + '%'

        const wUtilization = document.getElementById('widgetUtilization')
        if (wUtilization) wUtilization.innerText = s.utilization + '%'
        const wUtilizationSub = document.getElementById('widgetUtilizationSub')
        if (wUtilizationSub) wUtilizationSub.innerText = s.cars_used > 0 ? 'Sedang Beroperasi' : 'Siap Bertugas'
    } catch (err) {}
}

document.addEventListener('DOMContentLoaded', function(){
    const depart = document.querySelector('[name="date"]')
    const ret = document.querySelector('[name="return_date"]')
    if(depart && ret){
        const syncReturnDate = () => {
            ret.min = depart.value || ''
            if(depart.value && (!ret.value || ret.value < depart.value)) ret.value = depart.value
        }
        depart.addEventListener('change', syncReturnDate)
        syncReturnDate()
    }
    
    // Start real-time background polling every 4 seconds
    setInterval(updateRealTimeStats, 4000)
})
