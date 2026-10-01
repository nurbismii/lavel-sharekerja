const notify = (message) => { const box = document.querySelector('#feedback'); if (!box) return; box.textContent = message; box.hidden = false; setTimeout(() => { box.hidden = true; }, 5000); };
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
document.querySelectorAll('[data-share]').forEach(button => button.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(button.dataset.share); notify('Tautan lowongan disalin.'); await fetch(button.dataset.track, {method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}); }
    catch { notify('Belum bisa menyalin otomatis. Salin alamat halaman dari browser.'); }
}));
document.querySelectorAll('[data-wizard]').forEach(form => {
    const steps = [...form.querySelectorAll('[data-step]')]; let current = 0;
    const previous=form.querySelector('[data-previous]'), next=form.querySelector('[data-next]');
    form.querySelector('.wizard-controls').hidden = false;
    const render = () => {
        steps.forEach((step,index)=>{step.hidden=index!==current;});
        form.querySelector('.wizard-progress').textContent = 'Langkah '+(current+1)+' dari '+steps.length;
        previous.disabled=current===0; next.hidden=current===steps.length-1;
        const preview=form.querySelector('[data-preview]'); preview.replaceChildren();
        for(const [key,label] of Object.entries({title:'Posisi',location:'Lokasi',method:'Metode',deadline:'Deadline'})) { const dt=document.createElement('dt'), dd=document.createElement('dd'); dt.textContent=label; dd.textContent=form.elements[key].value || 'Belum diisi'; preview.append(dt,dd); }
    };
    previous.addEventListener('click',()=>{current=Math.max(0,current-1);render();});
    next.addEventListener('click',()=>{const invalid=[...steps[current].querySelectorAll('input,select,textarea')].find(input=>!input.checkValidity()); if(invalid){invalid.reportValidity();return;} current=Math.min(steps.length-1,current+1);render();});
    form.addEventListener('invalid',event=>{const index=steps.findIndex(step=>step.contains(event.target)); if(index>=0){current=index;render();}},true);
    render();
});
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button=>{button.disabled=true;button.setAttribute('aria-busy','true');button.textContent='Memproses…';});}));
if (navigator.share) document.querySelectorAll('[data-native-share]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', async () => {
        try { await navigator.share({title:button.dataset.title,url:button.dataset.nativeShare}); fetch(button.dataset.track,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).catch(()=>{}); }
        catch (error) { if (error.name !== 'AbortError') notify('Bagikan belum tersedia. Gunakan salin tautan.'); }
    });
});
