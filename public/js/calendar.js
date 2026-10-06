'use strict';
(() => {
 const root = document.querySelector('[data-month-calendar]');
 if (!root || !window.fetch || !window.HTMLDialogElement) return;
 const form = root.querySelector('form'), month = form.elements.month, room = form.elements.room_id;
 const content = root.querySelector('[data-month-content]'), status = root.querySelector('[role="status"]');
 const dialog = root.querySelector('dialog'), detail = dialog.querySelector('[data-detail]');
 const labels = {free:'Libre', reserved:'Reservada', expected:'Ocupada prevista', inactive:'Inactiva', occupied:'Estadía registrada'};
 const fmt = value => new Intl.DateTimeFormat('es-AR', {dateStyle:'medium'}).format(new Date(value + 'T12:00:00'));
 let controller, data, trigger;
 function node(tag, text, cls) { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; if(cls) n.className = cls; return n; }
 function showDetail(reservation, item) {
   trigger = document.activeElement;
   detail.replaceChildren(node('h2', 'Reserva #' + reservation.id), node('p', reservation.guest_name),
     node('p', 'Habitación ' + item.code + ' · ' + item.name), node('p', 'Estado: confirmada'));
   if(reservation.check_in) detail.append(node('p', 'Entrada prevista: ' + fmt(reservation.check_in)), node('p', 'Salida prevista: ' + fmt(reservation.check_out)));
   if(reservation.checked_in_at) detail.append(node('p', 'Llegada registrada: ' + reservation.checked_in_at), node('p', 'Salida registrada: ' + (reservation.checked_out_at || 'Todavía no registrada')));
   const link = node('a', 'Abrir reserva para gestionarla', 'button');
   link.href = 'solicitud.php?id=' + encodeURIComponent(reservation.id); detail.append(link);
   dialog.showModal();
 }
 function render(next) {
   const fragment = document.createDocumentFragment();
   fragment.append(node('h2', new Intl.DateTimeFormat('es-AR',{month:'long',year:'numeric'}).format(new Date(next.from+'T12:00:00'))));
   if (!next.rooms.length) fragment.append(node('p','No hay habitaciones para mostrar.', 'notice'));
   else {
     const grid = node('div', undefined, 'month-grid');
     ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'].forEach(day => grid.append(node('div',day,'month-weekday')));
     const offset = (new Date(next.from+'T12:00:00').getDay()+6)%7;
     for(let i=0;i<offset;i++) { const blank=node('div',undefined,'month-blank'); blank.setAttribute('aria-hidden','true'); grid.append(blank); }
     const last = Number(new Date(Number(next.from.slice(0,4)),Number(next.from.slice(5,7)),0).getDate());
     for(let i=1;i<=last;i++) {
       const date=next.from.slice(0,8)+String(i).padStart(2,'0');
       const day=node('section',undefined,'month-day');
       const heading=node('h3',fmt(date)+(date===next.today?' · Hoy':'')); day.append(heading);
       if(date===next.today) day.setAttribute('aria-current','date');
       if(date>next.limit) { day.append(node('p','Fuera del límite anual')); day.classList.add('month-unavailable'); grid.append(day); continue; }
       for(const item of next.rooms) {
         const cell=next.cells[item.id][date], block=node('div',undefined,'month-room');
         block.append(node('span',item.code+' · '+labels[cell.state]+(!Number(item.active)?' · Habitación inactiva':''),'calendar-state '+cell.state));
         const entries=new Map();
         for(const [key,prefix] of [['stays','Reserva'],['arrivals','Entrada prevista'],['departures','Salida prevista'],['actuals','Registro real']]) {
           for(const res of cell[key]) {
             const entry=entries.get(res.id) || {reservation:{}, labels:[]};
             Object.assign(entry.reservation,res); entry.labels.push(prefix); entries.set(res.id,entry);
           }
         }
         for(const entry of entries.values()) {
           const button=node('button',entry.labels.join(' / ')+' #'+entry.reservation.id+' · '+entry.reservation.guest_name,'month-reservation');
           button.type='button'; button.addEventListener('click',()=>showDetail(entry.reservation,item)); block.append(button);
         }
         day.append(block);
       }
       grid.append(day);
     }
     fragment.append(grid);
   }
   content.replaceChildren(fragment);
   root.querySelector('[data-shift="-1"]').disabled=next.from.slice(0,7)<='2000-01';
   root.querySelector('[data-shift="1"]').disabled=next.from.slice(0,7)>=next.limit.slice(0,7);
 }
 async function load() {
   if(!form.reportValidity()) return;
   controller?.abort(); const request=new AbortController(); controller=request;
   const timeout=setTimeout(()=>request.abort(),12000);
   content.setAttribute('aria-busy','true'); status.textContent='Consultando el mes…';
   const query=new URLSearchParams({month:month.value,room_id:room.value});
   try {
     const response=await fetch('calendario-datos.php?'+query,{signal:request.signal,credentials:'same-origin',cache:'no-store'});
     const next=await response.json();
     if(!response.ok) throw new Error(next.error || 'No se pudo consultar el calendario.');
     if(controller!==request) return;
     render(next); data=next;
     status.textContent='Datos actualizados. Cada día muestra la disponibilidad de esa noche.';
   } catch(error) {
     if(controller!==request) return;
     status.textContent=(error.name==='AbortError'?'La consulta tardó demasiado.':error.message)+' '+(data?'Se conserva el último mes cargado. ':'')+'Podés reintentar con Ver mes o usar la tabla por fechas.';
   } finally { clearTimeout(timeout); if(controller===request) content.removeAttribute('aria-busy'); }
 }
 form.addEventListener('submit',event=>{event.preventDefault();load();});
 room.addEventListener('change',load);
 root.querySelectorAll('[data-shift]').forEach(button=>button.addEventListener('click',()=>{
   const date=new Date(month.value+'-01T12:00:00'); date.setMonth(date.getMonth()+Number(button.dataset.shift));
   month.value=date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0'); load();
 }));
 root.querySelector('[data-today]').addEventListener('click',()=>{month.value=root.dataset.today.slice(0,7);load();});
 dialog.querySelector('[data-close]').addEventListener('click',()=>dialog.close());
 dialog.addEventListener('close',()=>trigger?.focus());
 root.hidden=false; load();
})();
