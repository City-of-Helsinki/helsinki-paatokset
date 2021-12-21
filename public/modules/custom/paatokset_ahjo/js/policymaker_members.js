/**
 * @file
 * meetings_calendar.js
 *
 * Creates vue applcation for the meetings calendar
 */

 (function($, Drupal) {

  Drupal.behaviors.paatoksetAhjoPolicymakerMembers = {
    attach() {
      const markup = `
      <div class="policymaker-members container">
        <div class="policymaker-members__filters">
          <div class="search-wrapper">
            <label>Hae valtuutettua</label>
            <input type="text" v-model="search" placeholder="Syötä valtuutetun nimi"/>
          </div>
          <div v-for="(filter, key) in filters" class="form-item">
            <div class="form-item__dropdown">
              <label>{{ filter.label }}</label>
              <select class="form-item__select" id="select" v-model="active_filters[key]">
                <option :value="Object.keys(filter)[0]">{{Object.keys(filter)[0]}}</option>
                <option v-for="value in Object.values(filter)[0]" v-bind:value="value">{{ value }}</option>
              </select>
            </div>
          </div>
          <div class="filters-checkboxes">
              <li v-for="checkbox in checkboxes" class="form-item--checkbox__item">
                <label :for="Object.keys(checkbox)[0]">
                  <input :checked="active_checkboxes.includes(Object.keys(checkbox)[0])" :id="Object.keys(checkbox)[0]" name="checkbox" type="checkbox" @click="addToActiveCheckbox(Object.keys(checkbox)[0])">
                  <span>{{ Object.values(checkbox)[0] }}</span>
                </label>
              </li>
            </div>
        </div>
        <div class="policymaker-members__list">
          <div v-for="member in filteredMembers" class="member-row">
            <div class="member-info">
              <span class="member-name">{{ member.name }}</span>
              <div>
                <span class="member-role">{{ member.role }}</span>
                <span class="member-party"> {{ member.party }}</span>
              </div>
              <span class="member-email">{{ processEmail(member.email) }}</span>
            </div>
            <div class="member-image"></div>
          </div>
        </div>
      </div>
      `

      new Vue({
        el: '#policymaker-members-vue',
        template: markup,
        data: {
          members: [
            {
              'name': 'Aki Hyödynmaa',
              'role': 'Varajäsen',
              'links': [],
              'deputyOf': 'Salla Korhonen',
              'email' : 'etunimi.sukunimi@email.com',
              'party' : 'Vasemmisto'
            },
            {
              'name': 'Antti Vuorela',
              'role': 'Varapuheenjohtaja',
              'links': [],
              'deputyOf': null,
              'email' : 'etunimi.sukunimi@email.com',
              'party' : 'Kokoomus'
            },
            {
              'name': 'Mikko Mallikas',
              'role': 'Varajäsen',
              'links': [],
              'deputyOf': null,
              'email' : 'etunimi.sukunimi@email.com',
              'party' : 'Kokoomus'
            },
            {
              'name': 'Testi Testinen',
              'role': 'Joku rooli',
              'links': [],
              'deputyOf': null,
              'email' : 'etunimi.sukunimi@email.com',
              'party' : 'Kokoomus'
            },
            {
              'name': 'Joku Jokunen',
              'role': 'Varajäsen',
              'links': [],
              'deputyOf': null,
              'email' : 'etunimi.sukunimi@email.com',
              'party' : 'Kokoomus'
            }
          ],
          isReady: false,
          search: '',
          filters: {
            party: { 'Kaikki': ['Kokoomus', 'Vasemmisto'], label : 'Rajaa puolueen mukaan' },
            order: { 'A-Ö, nimen mukaan': ['A-Ö, puolueen mukaan'], label: 'Lajittelu'}
          },
          active_filters: {
            party: 'Kaikki',
            order: 'A-Ö, nimen mukaan'
          },
          checkboxes: {
            deputy_member: { deputy_member: 'Näytä varajäsenet'}
          },
          active_checkboxes: [],
        },
        methods: {
          addToActiveCheckbox(value) {
            let temp_filters = this.active_checkboxes;
            if(!temp_filters.includes(value)) {
              temp_filters.push(value)
            } else {
              const index = temp_filters.indexOf(value);
              if (index !== -1) {
                temp_filters.splice(index, 1);
              }
            }
            this.active_checkboxes = temp_filters;
          },
          processEmail(email) {
            return email.replace("@", " (at) ")
          }
        },
        computed: {
          filteredMembers() {
            let temp_results = this.members;
            temp_results = temp_results.filter(result => {
              return result.name.toLowerCase().includes(this.search.toLowerCase())
            })

            for (var i = 0; i < this.active_checkboxes.length; i++) {
              if(this.active_checkboxes[i] === 'deputy_member') {
                temp_results = temp_results.filter(result => result.role === 'Varajäsen')
              }
            }

            if(this.active_filters.party !== 'Kaikki') {
              temp_results = temp_results.filter(result => result.party === this.active_filters.party)
            }

            return temp_results;
          }
        }
      });
    }
  }

}(jQuery, Drupal));
