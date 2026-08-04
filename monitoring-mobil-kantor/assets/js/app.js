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

        const driverOptions = (data.drivers || []).map(d =>
            `<option value="${d.id}" ${parseInt(b.driver_id) === parseInt(d.id) ? 'selected' : ''}>${escHtml(d.name)}</option>`
        ).join('')

        body.innerHTML = `
            <div class="detail-grid" style="margin-bottom:16px">
                <div class="detail-item"><span>Kode Booking</span><strong>${escHtml(b.code)}</strong></div>
                <div class="detail-item"><span>Status</span><strong>${escHtml(b.status_label)}</strong></div>
                <div class="detail-item"><span>Tanggal Berangkat</span><strong>${escHtml(b.date_label)}</strong></div>
                <div class="detail-item"><span>Tanggal Pulang</span><strong>${escHtml(b.return_date_label)}</strong></div>
                <div class="detail-item"><span>Jam</span><strong>${escHtml(b.start_time)} - ${escHtml(b.end_time)}</strong></div>
                <div class="detail-item"><span>Mobil</span><strong>${escHtml(b.car_name || '-')}</strong><small>${escHtml(b.plate_number || '')}</small></div>
            </div>

            <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px">
                <h3 style="margin-bottom:14px;font-size:14px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:6px">
                    ✏️ Input / Edit Detail Booking
                </h3>
                <div class="form-grid" style="gap:12px">
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700">Nama Driver</label>
                        <select id="modal_driver_id" class="input" style="font-size:13px;padding:8px 10px">
                            <option value="">-- Pilih Driver --</option>
                            ${driverOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700">Durasi (Hari)</label>
                        <input id="modal_durasi" class="input" type="number" min="1" value="${escHtml(String(b.durasi_hari || 1))}" style="font-size:13px;padding:8px 10px">
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700">Tujuan</label>
                        <input id="modal_destination" class="input" type="text" value="${escHtml(b.destination || '')}" placeholder="Tujuan perjalanan" style="font-size:13px;padding:8px 10px">
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700">Uang Muka (UMK)</label>
                        <input id="modal_advance" class="input" type="number" min="0" step="any" value="${b.advance_amount || 0}" readonly style="font-size:13px;padding:8px 10px;background:var(--bg-subtle)">
                        <small class="text-muted" style="font-size:11px">Terkoneksi dengan booking mobil (read-only)</small>
                    </div>
                </div>
                <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
                    <button class="btn btn-success" onclick="saveBookingModal(${b.id})" style="font-size:13px;padding:8px 18px">💾 Simpan Perubahan</button>
                    <span id="modal_save_msg" style="font-size:12px;font-weight:600"></span>
                </div>
            </div>

            <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px">
                <h3 style="font-size:13px;font-weight:700;margin-bottom:10px;color:var(--text-2)">📊 Ringkasan Keuangan Perjalanan</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
                    <div style="padding:8px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                        <div style="color:var(--muted);font-size:11.5px;margin-bottom:2px">UMK Diberikan</div>
                        <strong style="color:var(--blue)">${escHtml(b.advance_label || 'Rp 0')}</strong>
                    </div>
                    <div style="padding:8px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                        <div style="color:var(--muted);font-size:11.5px;margin-bottom:2px">Realisasi (Nota)</div>
                        <strong style="color:var(--text)">${escHtml(b.nota_total_label || 'Rp 0')}</strong>
                    </div>
                    <div style="padding:8px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border);grid-column:span 2">
                        <div style="color:var(--muted);font-size:11.5px;margin-bottom:2px">Lebih / Kurang UMK</div>
                        <strong style="color:${(b.selisih_umk || 0) < 0 ? 'var(--red)' : 'var(--green)'}">${escHtml(b.selisih_umk_label || 'Rp 0')}</strong>
                    </div>
                </div>
            </div>

            <h3 style="margin-top:14px;font-size:13px;font-weight:700">Penumpang</h3>
            <p style="font-size:13px;margin-bottom:14px">${data.passengers.length ? data.passengers.map(p=>escHtml(p.name)).join(', ') : '-'}</p>
            <div class="actions" style="margin-top:14px">
                <a class="btn btn-primary" href="booking_detail.php?id=${b.id}">Buka Detail Lengkap</a>
            </div>
        `
    }catch(err){
        body.innerHTML = '<p>Gagal memuat detail.</p>'
    }
}

function escHtml(str){
    if(str == null) return ''
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
}

async function saveBookingModal(bookingId){
    const driverId    = document.getElementById('modal_driver_id')?.value
    const durasi      = document.getElementById('modal_durasi')?.value
    const requester   = document.getElementById('modal_requester')?.value
    const destination = document.getElementById('modal_destination')?.value
    const msg         = document.getElementById('modal_save_msg')
    if(msg){ msg.textContent = 'Menyimpan...'; msg.style.color = 'var(--muted)' }

    try{
        const res = await fetch('api/update_booking_modal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: bookingId,
                driver_id: driverId,
                durasi: durasi,
                requester_name: requester,
                destination: destination
            })
        })
        const data = await res.json()
        if(data.ok){
            if(msg){ msg.textContent = '✅ Tersimpan!'; msg.style.color = 'var(--green)' }
            setTimeout(() => { location.reload() }, 1000)
        } else {
            if(msg){ msg.textContent = '❌ ' + (data.message || 'Gagal'); msg.style.color = 'var(--red)' }
        }
    }catch(err){
        if(msg){ msg.textContent = '❌ Error jaringan'; msg.style.color = 'var(--red)' }
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

async function openDayBookingsModal(date) {
    const body = document.getElementById('bookingModalBody')
    if (!body) return
    body.innerHTML = '<p class="text-muted" style="padding:20px;text-align:center">Memuat jadwal booking...</p>'
    openModal('bookingModal')
    try {
        const res = await fetch('api/day_bookings_json.php?date=' + encodeURIComponent(date))
        const data = await res.json()
        if (!data.ok) { body.innerHTML = `<p>${escHtml(data.message || 'Gagal memuat')}</p>`; return }

        const dateLabel = data.date_label || date
        const bookings  = data.bookings || []
        const drivers   = data.drivers  || []

        let html = `
            <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <h3 style="font-size:18px;font-weight:800;color:var(--text);margin:0">📅 Jadwal Booking: ${escHtml(dateLabel)}</h3>
                    <p class="text-muted" style="font-size:12px;margin:2px 0 0 0">Total ${bookings.length} booking pada tanggal ini.</p>
                </div>
                <a class="btn btn-primary" href="booking.php" style="font-size:12.5px;padding:6px 14px">+ Ajukan Booking Baru</a>
            </div>
        `

        if (!bookings.length) {
            html += `
                <div style="text-align:center;padding:40px 20px;background:var(--bg);border-radius:12px;border:1px dashed var(--border)">
                    <span style="font-size:36px;display:block;margin-bottom:8px">🚗💨</span>
                    <h4 style="font-size:15px;font-weight:700;margin:0 0 4px">Belum ada booking pada tanggal ini</h4>
                    <p class="text-muted" style="font-size:13px;margin:0">Klik tombol di atas untuk membuat permohonan booking baru.</p>
                </div>
            `
        } else {
            html += `
                <div style="overflow-x:auto;border:1px solid var(--border);border-radius:10px;background:var(--bg)">
                    <table class="table" style="font-size:12.5px;white-space:nowrap;margin:0">
                        <thead>
                            <tr style="background:var(--bg-subtle)">
                                <th style="padding:10px 8px">MOBIL</th>
                                <th style="padding:10px 8px">DRIVER</th>
                                <th style="padding:10px 8px">TUJUAN</th>
                                <th style="padding:10px 8px">USER (PENGGUNA)</th>
                                <th style="padding:10px 8px">BIDANG (DEPT)</th>
                                <th style="padding:10px 8px;min-width:120px">UMK (UANG MUKA)</th>
                                <th style="padding:10px 8px;min-width:130px">REALISASI (NOTA)</th>
                                <th style="padding:10px 8px">LEBIH / KURANG</th>
                                <th style="padding:10px 8px">STATUS</th>
                                <th style="padding:10px 8px">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
            `

            bookings.forEach(b => {
                const notaTotal  = parseFloat(b.nota_total  || 0)
                const advanceAmt = parseFloat(b.advance_amount || 0)
                const selisih    = advanceAmt - notaTotal
                const selisihClass = selisih < 0 ? 'color:var(--red)' : 'color:var(--green)'

                const statusColors = {
                    'pending':   { bg: 'var(--orange-light,#fff3e0)', color: '#e65100', label: 'Menunggu' },
                    'approved':  { bg: 'var(--blue-light,#e3f2fd)',   color: '#1565c0', label: 'Disetujui' },
                    'running':   { bg: 'var(--green-light,#e8f5e9)',  color: '#2e7d32', label: 'Berjalan' },
                    'completed': { bg: '#f3e5f5',                     color: '#6a1b9a', label: 'Selesai' },
                    'rejected':  { bg: '#ffebee',                     color: '#b71c1c', label: 'Ditolak' }
                }
                const st = statusColors[b.status] || { bg: '#f5f5f5', color: '#555', label: b.status }

                html += `
                    <tr id="day_row_${b.id}" style="transition:background .2s">
                        <td style="padding:10px 8px;vertical-align:middle">
                            <strong style="font-size:13px">${escHtml(b.car_name || 'Mobil belum dipilih')}</strong>
                            <small class="text-muted" style="display:block">${escHtml(b.plate_number || '')}</small>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <strong style="font-size:13px">${escHtml(b.driver_name || 'Driver belum dipilih')}</strong>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <span style="font-size:13px;font-weight:600;color:var(--text)">${escHtml(b.destination || '-')}</span>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <strong>${escHtml(b.requester)}</strong>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <span>${escHtml(b.department || '-')}</span>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <input type="hidden" id="day_adv_${b.id}" value="${advanceAmt}">
                            <strong style="font-size:13px;color:var(--primary)">${formatRupiah(advanceAmt)}</strong>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <input id="day_nota_${b.id}" class="input" type="number" min="0" step="any"
                                value="${notaTotal}"
                                oninput="calcDaySelisih(${b.id})"
                                style="font-size:12px;padding:5px 8px;width:120px;border-radius:6px">
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <strong id="day_selisih_${b.id}" style="font-size:13px;${selisihClass}">${formatRupiah(selisih)}</strong>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:${st.bg};color:${st.color}">${st.label}</span>
                        </td>
                        <td style="padding:10px 8px;vertical-align:middle">
                            <button class="btn btn-success" style="font-size:11.5px;padding:5px 10px;margin-bottom:4px;display:block;width:100%;white-space:nowrap" onclick="saveSingleDayBooking(${b.id})">💾 Simpan</button>
                            <a class="btn btn-outline" style="font-size:11px;padding:3px 8px;display:block;text-align:center;white-space:nowrap" href="booking_detail.php?id=${b.id}">🔗 Detail</a>
                            <span id="day_msg_${b.id}" style="font-size:11px;display:block;margin-top:3px;font-weight:600;text-align:center"></span>
                        </td>
                    </tr>
                `
            })

            html += `
                        </tbody>
                    </table>
                </div>
            `
        }

        body.innerHTML = html

    } catch(err) {
        body.innerHTML = '<p>Gagal memuat detail jadwal.</p>'
    }
}

function calcDaySelisih(id) {
    const adv = parseFloat(document.getElementById(`day_adv_${id}`)?.value || 0)
    const notaTotal = parseFloat(document.getElementById(`day_nota_${id}`)?.value || 0)
    const selisih = adv - notaTotal
    const el = document.getElementById(`day_selisih_${id}`)
    if (el) {
        el.textContent = formatRupiah(selisih)
        el.style.color = selisih < 0 ? 'var(--red)' : 'var(--green)'
    }
}

async function saveSingleDayBooking(bookingId) {
    const realisasi = document.getElementById(`day_nota_${bookingId}`)?.value
    const msg       = document.getElementById(`day_msg_${bookingId}`)

    if (msg) { msg.textContent = 'Menyimpan...'; msg.style.color = 'var(--muted)' }

    try {
        const res = await fetch('api/update_booking_modal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: bookingId,
                realisasi:  realisasi
            })
        })
        const data = await res.json()
        if (data.ok) {
            if (msg) { msg.textContent = '✅ Tersimpan!'; msg.style.color = 'var(--green)' }
            setTimeout(() => { if (msg) msg.textContent = '' }, 2500)
        } else {
            if (msg) { msg.textContent = '❌ ' + (data.message || 'Gagal'); msg.style.color = 'var(--red)' }
        }
    } catch(err) {
        if (msg) { msg.textContent = '❌ Error'; msg.style.color = 'var(--red)' }
    }
}

function formatRupiah(amount) {
    return 'Rp ' + Number(amount || 0).toLocaleString('id-ID')
}


async function pollNotifCount() {
    try {
        const res = await fetch('api/notif_count.php')
        const data = await res.json()
        if (!data.ok) return
        const visibleCount = data.count || 0
        const badge = document.getElementById('notifCountBadge')
        const bellBadge = document.querySelector('#notifBtn .notif-badge')
        const emptyEl = document.getElementById('notifEmpty')
        const footerEl = document.getElementById('notifFooter')
        if (badge) badge.textContent = visibleCount + ' baru'
        if (visibleCount === 0) {
            if (badge) badge.style.display = 'none'
            if (bellBadge) bellBadge.style.display = 'none'
            if (emptyEl) emptyEl.style.display = 'flex'
            if (footerEl) footerEl.style.display = 'none'
        } else {
            if (badge) badge.style.display = ''
            if (bellBadge) bellBadge.style.display = ''
            if (emptyEl) emptyEl.style.display = 'none'
            if (footerEl) footerEl.style.display = ''
        }
    } catch (err) {}
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof updateRealTimeStats === 'function') updateRealTimeStats();
    if (typeof pollNotifCount === 'function') pollNotifCount();

    setInterval(function() {
        if (typeof updateRealTimeStats === 'function') updateRealTimeStats();
        if (typeof pollNotifCount === 'function') pollNotifCount();
    }, 30000);
});
