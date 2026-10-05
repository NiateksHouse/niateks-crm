'use strict';
const $=id=>document.getElementById(id);
function startMusic(){
 let ctx=null,gain=null,timer=null,playing=false,muted=false,index=0,run=0,nodes=new Set();
 const styles={jazz:{label:'Jazz',beats:650,chords:[[48,55,59,62,64],[45,52,55,59,62],[50,57,60,64,65],[43,50,53,57,59]],wave:'sine'},relax:{label:'Relax',beats:1500,chords:[[48,55,62],[45,52,59],[41,48,55],[43,50,57]],wave:'sine'},acoustic:{label:'Akustik',beats:500,chords:[[48,52,55,60],[45,48,52,57],[41,45,48,53],[43,47,50,55]],wave:'triangle'}};
 function volume(){return muted?0:Number($('music-volume').value)/100*.22}
 function sync(){ $('music-play').textContent=playing?'Duraklat':'Çal';$('music-mute').textContent=muted?'Sesi aç':'Sessize al';$('music-mute').setAttribute('aria-pressed',String(muted));$('music-volume-value').textContent=$('music-volume').value+'%';if(gain)gain.gain.setTargetAtTime(volume(),ctx.currentTime,.04)}
 function clear(){if(timer){clearTimeout(timer);timer=null}for(const n of nodes){try{n.stop()}catch{}}nodes.clear()}
 function tone(note,time,duration,level,wave){const osc=ctx.createOscillator(),env=ctx.createGain();osc.type=wave;osc.frequency.value=440*Math.pow(2,(note-69)/12);env.gain.setValueAtTime(0,time);env.gain.linearRampToValueAtTime(level,time+.025);env.gain.exponentialRampToValueAtTime(.0001,time+duration);osc.connect(env);env.connect(gain);osc.start(time);osc.stop(time+duration+.05);nodes.add(osc);osc.onended=()=>{nodes.delete(osc);osc.disconnect();env.disconnect()}}
 function tick(){if(!playing)return;const s=styles[$('music-style').value],chord=s.chords[Math.floor(index/8)%4],t=ctx.currentTime+.02;if(s.label==='Relax'){chord.forEach((n,i)=>tone(n,t+i*.1,3,.11,s.wave))}else if(s.label==='Akustik'){tone(chord[index%4],t,1.4,.24,s.wave);if(index%4===0)tone(chord[0]-12,t,1.8,.16,'sine')}else{if(index%4===0)chord.forEach((n,i)=>tone(n,t+i*.015,1.1,.10,'sine'));tone(chord[index%chord.length]+12,t+(index%2?.08:0),.65,.13,'sine');tone(chord[0]-12,t,.5,.18,'sine')}index++;timer=setTimeout(tick,s.beats)}
 async function play(){const request=++run;if(playing){playing=false;clear();if(ctx)await ctx.suspend();$('music-status').textContent='Duraklatıldı';sync();return}try{if(!ctx){const AC=window.AudioContext||window.webkitAudioContext;if(!AC)throw Error();ctx=new AC();gain=ctx.createGain();gain.connect(ctx.destination)}await ctx.resume();if(request!==run)return;playing=true;sync();$('music-status').textContent=styles[$('music-style').value].label+' · müzik önizlemesi çalıyor';tick()}catch{$('music-status').textContent='Ses başlatılamadı. Tarayıcıda ses iznini kontrol edip tekrar deneyin.'}}
 $('music-play').onclick=play;$('music-stop').onclick=async()=>{run++;playing=false;clear();index=0;if(ctx)await ctx.suspend();$('music-status').textContent='Durduruldu';sync()};$('music-mute').onclick=()=>{muted=!muted;sync()};$('music-volume').oninput=()=>{sync()};$('music-style').onchange=()=>{clear();index=0;if(playing){$('music-status').textContent=styles[$('music-style').value].label+' · müzik önizlemesi çalıyor';tick()}};sync();window.addEventListener('pagehide',()=>{playing=false;clear();if(ctx)ctx.close()});
}

const breakMessages=[
 "Bilgisayarından mesaj var: Sen kahve al, ben burada işleri tutarım.",
 "Uff, iki dakika soluklanalım mı? İşlemcim adına konuşamam ama ben mola taraftarıyım.",
 "Bu sandalyeyle fazla samimi olduk. İstersen biraz ayağa kalkıp ortamı değiştir.",
 "Omuzları biraz serbest bırakalım. Bugünün bütün işlerini onların taşımasına gerek yok.",
 "Küçük bir gülümseme molası verelim mi? Always smile.",
 "Kahve, çay ya da bir bardak su? Mola menüsünü sana bırakıyorum.",
 "Fincanın seni merak ediyor olabilir. Kısa bir ziyaret ister misin?",
 "Kahve toplantısı öneriyorum. Gündem: hiçbir şey.",
 "Bir çay molası kadar boşluk bırakalım mı bugüne?",
 "Benim fincanım yok. Benim yerime de bir yudum keyif alır mısın?",
 "Kahven soğumadan bir bak. Bu kez hatırlatma gerçekten sıcak bir konu.",
 "Bugünün küçük lüksü: içeceğini acele etmeden içmek.",
 "Çay kaşığı mesaiye hazır; senin molanı bekliyor.",
 "Kahveye şeker şart değil. Molaya biraz keyif yeter.",
 "Fincanla kısa bir durum değerlendirmesi yapmaya ne dersin?",
 "Bir bardak suyun toplantı davetini kabul edelim mi?",
 "Su şişen görünür bir yerde mi? Kendisine biraz ilgi gösterebiliriz.",
 "Bazen en iyi yenileme düğmesi bir yudum sudur.",
 "Ekran bende, su molası sende. Anlaştık mı?",
 "Mola için büyük plan gerekmiyor. Bir bardak su ve iki dakika yeter.",
 "Pencerenin dışında hangi renkler var? Ekranın renklerine kısa bir alternatif.",
 "Biraz uzağa bakmak ister misin? Dünya bu pencereye sığmıyor.",
 "Sekmeler arasında değil, odada küçük bir tur atalım mı?",
 "Koridora kadar küçük bir keşif gezisi? Dönüşte yerin hazır.",
 "İstersen ayağa kalkıp manzarayı bir de o yükseklikten gör.",
 "Sandalye burada kalıyor. Sen kısa bir gezintiye çıkabilirsin.",
 "Masadan birkaç adım uzaklaşınca masa kaçmıyor. Denendi sayılmaz, ama umutluyum.",
 "Bugünkü en kısa rota: masa, pencere, masa. Rehberlik ücretsiz.",
 "Bir sonraki fikri ayakta karşılamayı deneyelim mi?",
 "Klavye başında küçük bir perde arası.",
 "Omuzların kulaklarına misafirliğe gitmiş olabilir. Rahat bir konuma dönelim mi?",
 "Ellerine kısa bir izin ver. Bugün çok düğmeye dokundular.",
 "İstersen oturuşunu değiştirip biraz rahat yerleş.",
 "Bir an durup rahatça nefes alalım. Acelemiz bu mesajda yok.",
 "Yüzündeki ciddi toplantıyı bir gülümsemeyle dağıtabiliriz.",
 "Dilersen çeneni gevşet, omuzlarını bırak. Bu satırın acelesi yok.",
 "Sırtını rahatça yerleştir. Sandalye de görevini yapsın.",
 "Parmaklar için kısa bir tatil. Uçak bileti gerekmiyor.",
 "Ekrandan gözünü ayırıp sevdiğin bir şeye bakmaya ne dersin?",
 "Rahat bir pozisyon da çalışma düzeninin parçası olabilir.",
 "Her işi tek nefeste bitirmek zorunda değilsin.",
 "Küçük adımlar da ilerlemedir. Araya küçük bir mola sığar.",
 "Bugün kendine de nazik davranmayı yapılacaklar listesine ekleyebilirsin.",
 "Biraz durmak, vazgeçmek demek değil. Yalnızca mola.",
 "Sıradaki işe geçmeden önce kendine bir dakika ayırmak ister misin?",
 "Yapılacaklar listesi uzun olabilir; bu mola kısa olabilir.",
 "Her şey aynı anda çözülmek zorunda değil. Bir adım seçmek yeter.",
 "Bugüne biraz nefes payı bırakalım mı?",
 "Küçük bir ara, sonra kaldığımız yerden.",
 "Kendine ayırdığın iki dakika için açıklama yazman gerekmiyor.",
 "Benim favori kısayolum: kısa yolculukla mutfağa gitmek.",
 "Ctrl + kahve diye bir tuş yok. Ama fikir güzel.",
 "Mola.exe çalıştırılsın mı? Kurulum gerektirmiyor.",
 "Gülümseme güncellemesi hazır. Yeniden başlatma istemiyorum.",
 "Bugünkü önerim: fareyi biraz kendi haline bırak.",
 "Klavye sessizliği de güzel bir müzik türü olabilir.",
 "Şaka bir yana, iki dakika hiçbir düğmeye basmamak hoş olabilir.",
 "Ben piksellerle idare ediyorum. Senin mola seçeneklerin daha güzel.",
 "Ekran parlaklığı tamam; biraz da gününe keyif ekleyelim.",
 "Yeni sekme önerisi: pencerenin dışındaki dünya.",
 "Bu işin dikiş payı varsa, günün de mola payı olsun.",
 "İplikler gibi düşünceler de bazen biraz gevşeklik ister.",
 "Bugünün desenine küçük bir kahve lekesi değil, kahve molası ekleyelim.",
 "Özenle yapılan işe, özenli bir mola yakışır.",
 "En güzel koleksiyonda bile parçalar arasında boşluk var.",
 "Numune molası: iki dakika. Beğenirsen başka gün yine deneriz.",
 "Bugünün renk kartına biraz pencere ışığı ekleyelim mi?",
 "Kumaşa dokunmak mümkünse, ekrandan farklı bir his iyi bir değişiklik olabilir.",
 "Bir fincanlık ara. Özel paketleme gerekmiyor.",
 "Mola siparişiniz hazır. Teslim yeri: kendinize ayırdığınız iki dakika.",
 "Bir mesai arkadaşına gülümsemek için küçük bir ara ister misin?",
 "Yan masaya bir selam? Yeni bir toplantı başlatmadan tabii.",
 "İstersen molanı sessizce geçir. Her boşluğu konuşmayla doldurmak gerekmiyor.",
 "Bugünün güzel bir anını düşünmeye bir dakika ayıralım mı?",
 "Seni güldüren bir şeyi hatırladın mı? Benim mesaj da aday olabilir.",
 "Kendine küçük bir teşekkür edebilirsin. Uzun bir konuşma şart değil.",
 "Bir arkadaşına selam vermek için uygun bir an olabilir.",
 "Bugünün minik kutlaması: buradayız, devam ediyoruz.",
 "Gülümseme için büyük bir sebep şart değil.",
 "Always smile. Ama bugün canın istemiyorsa, sakin bir mola da olur.",
 "İstersen müziği biraz kıs ve birkaç dakika sessizliğe yer aç.",
 "Bir şarkı boyunca dinlenmek güzel bir mola ölçüsü olabilir.",
 "Mola ritmini sen seç: çay, yürüyüş ya da sessizlik.",
 "Jazz çalıyorsa küçük bir solo da molaya verelim.",
 "Bugünün temposunu bir tık düşürmek ister misin?",
 "Biraz akustik, biraz sakinlik, biraz da ekransız zaman.",
 "Bazen en iyi çalma listesi pencerenin dışındaki seslerdir.",
 "Şarkı değişirken sen de duruşunu değiştirebilirsin.",
 "Bugünün nakaratı aynı olmak zorunda değil. Kısa bir ara verelim mi?",
 "Müziği durdurup çevrene kulak vermek de bir seçenek.",
 "Masanın üstünde sevdiğin bir şey var mı? Bir an ona bak.",
 "Aklındaki fikri kısa bir yere not edip mola vermek ister misin?",
 "Bir sonraki adımı seç, sonra kendine kısa bir boşluk bırak.",
 "Mola sırasında yeni görev bulmak zorunlu değil.",
 "İki dakikalığına hiçbir şeyi yetiştirmeyelim mi?",
 "Takvimde boşluk olmasa da bir yudumluk yer bulunabilir.",
 "Bugünün küçük sürprizi: bu mesaj senden iş istemiyor.",
 "Bu bildirimde acil kelimesi yok. Sadece nazik bir mola teklifi var.",
 "Ben burada bekleyebilirim. İstersen biraz soluklan.",
 "Molanın raporunu istemiyorum. Keyfini çıkarman yeter."
];
const BreakPool={
 refresh(p,day){if(p.day!==day){p.day=day;p.sent=0}if(!Number.isInteger(p.sent)||p.sent<0)p.sent=0;return p},
 next(p,random=Math.random){
  if(!Array.isArray(p.bag)||!p.bag.length||p.bag.some(i=>!Number.isInteger(i)||i<0||i>=breakMessages.length)||new Set(p.bag).size!==p.bag.length){
   p.bag=breakMessages.map((_,i)=>i);
   for(let i=p.bag.length-1;i>0;i--){const j=Math.floor(random()*(i+1));[p.bag[i],p.bag[j]]=[p.bag[j],p.bag[i]]}
   if(p.bag.at(-1)===p.last)[p.bag[0],p.bag[p.bag.length-1]]=[p.bag.at(-1),p.bag[0]];
  }
  p.last=p.bag.pop();return breakMessages[p.last];
 }
};

function startBreakCompanion(){
 const key='koza-companion-v26-'+document.body.dataset.companionUser;
 const day=()=>new Date().toLocaleDateString('en-CA');
 const read=()=>{try{return BreakPool.refresh(JSON.parse(localStorage.getItem(key)||'{}'),day())}catch{return BreakPool.refresh({},day())}};
 const save=p=>{try{localStorage.setItem(key,JSON.stringify(p))}catch{}};
 let lastInput=Date.now(),lastTick=Date.now();
 for(const event of ['pointerdown','keydown','scroll'])document.addEventListener(event,()=>{lastInput=Date.now()},{passive:true});
 function hide(){$('break-card').hidden=true}
 function show(p,automatic){if(automatic){p.sent++;p.active=0} $('break-copy').textContent=BreakPool.next(p);save(p);$('break-card').hidden=false}
 async function update(){
  const now=Date.now(),elapsed=Math.min(now-lastTick,10000);lastTick=now;
  if(document.hidden||!document.hasFocus()||now-lastInput>60000)return;
  const work=()=>{const p=read();if(p.muted===day())return;p.active=(Number(p.active)||0)+elapsed;
   const editing=document.activeElement?.matches('input,textarea,select,[contenteditable="true"]');
   if(p.sent<4&&p.active>=50*60000&&now>Number(p.later||0)&&now-lastInput>5000&&!editing&&$('break-card').hidden)show(p,true);else save(p)};
  if(navigator.locks)await navigator.locks.request(key,work);else work();
 }
 $('break-demo').onclick=()=>show(read(),false);
 $('break-close').onclick=hide;
 $('break-later').onclick=()=>{const p=read();p.later=Date.now()+10*60000;save(p);hide()};
 $('break-today').onclick=()=>{const p=read();p.muted=day();save(p);hide()};
 setInterval(update,5000);
 document.addEventListener('keydown',event=>{if(event.key==='Escape')hide()});
}
startMusic();startBreakCompanion();
