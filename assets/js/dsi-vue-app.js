const app = Vue.createApp({
  data() {
    return {
      nyheter: [],
      aktivIndex: 0,
      fallback: false
    };
  },
  mounted() {
    fetch('/wp-json/nattevakten/v1/data')
      .then(res => res.json())
      .then(data => {
        if (Array.isArray(data)) {
          this.nyheter = data;
        } else {
          this.fallback = true;
          this.nyheter = [{
            tekst: 'kl 03:33 – Pjuskeby – Redaksjonen sover fortsatt.',
            image_url: null
          }];
        }

        this.startTicker();
      })
      .catch(err => {
        console.error('Feil ved lasting:', err);
        this.fallback = true;
        this.nyheter = [{
          tekst: 'kl 03:34 – Klarte ikke hente nyheter.',
          image_url: null
        }];
        this.startTicker();
      });
  },
  methods: {
    startTicker() {
      setInterval(() => {
        this.aktivIndex = (this.aktivIndex + 1) % this.nyheter.length;
      }, 6000); // Bytt nyhet hvert 6. sekund
    }
  },
  template: `
    <div class="ticker" v-if="nyheter.length">
      <div style="text-align:center;">
        <marquee scrollamount="5">
          {{ nyheter[aktivIndex]?.tekst || 'Ingen nyheter tilgjengelig.' }}
        </marquee>
        <div v-if="nyheter[aktivIndex]?.image_url" style="margin-top: 10px;">
          <img :src="nyheter[aktivIndex].image_url" alt="Nyhetsbilde" style="max-width:100%; border:1px solid #00ff99; border-radius:5px;">
        </div>
      </div>
    </div>
  `
});

app.mount('#nattevakt-app');
