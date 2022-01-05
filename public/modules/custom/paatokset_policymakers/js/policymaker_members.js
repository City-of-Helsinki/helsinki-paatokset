/**
 * @file
 * policymaker_members.js
 *
 * Creates vue applcation for the meetings calendar
 */

 (function($, Drupal) {

  Drupal.behaviors.paatoksetPolicymakersPolicymakerMembers = {
    attach() {
      const markup = `
      <div class="policymaker-members">
        <div class="policymaker-members__filters">
          <div class="search-wrapper">
            <label>{{ searchLabel }}</label>
            <input type="text" v-model="search" :placeholder="searchPlaceholder"/>
            <i class="hds-icon hds-icon--search"></i>
          </div>
          <div v-for="(filter, key) in filters" class="form-item">
            <div class="form-item__dropdown">
              <label>{{ filter.label }}</label>
              <select class="form-item__select" id="select" v-model="active_filters[key]">
                <option :value="Object.keys(filter)[0]">{{Object.keys(filter)[0]}}</option>
                <option v-for="value in Object.values(filter)[0]" v-bind:value="value">{{ value }}</option>
              </select>
              <i class="hds-icon hds-icon--angle-down"></i>
            </div>
          </div>
          <div v-if="hasDeputies" class="filters-checkboxes">
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
              <span class="member-name">{{ member.first_name }} {{ member.last_name }}</span>
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
      const policymakerID = document.querySelector('#block-paatoksetpolicymakermembers').dataset.policymaker;
      const dataURL = window.location.origin + '/fi/ahjo_api/org_composition/' + policymakerID;

      new Vue({
        el: '#policymaker-members-vue',
        template: markup,
        data: {
          members: [],
          isReady: false,
          search: '',
          filters: {
            party: { 'Kaikki': [], label : 'Rajaa puolueen mukaan' },
            order: { 'A-Ö, nimen mukaan': ['A-Ö, puolueen mukaan'], label: 'Lajittelu'}
          },
          active_filters: {
            party: 'Kaikki',
            order: 'A-Ö, nimen mukaan'
          },
          checkboxes: {
            deputy_member: { deputy_member: 'Näytä myös varajäsenet'}
          },
          active_checkboxes: [],
        },
        methods: {
          getJson() {
            const self = this;
            $.getJSON(dataURL, function(data) {
              self.members = data;

              const parties = data.map(a => a.party).filter(function (el) {
                return el != null;
              });
              self.filters.party['Kaikki'] = [...new Set(parties)];
            })
            .done(function( json ) {
              self.isReady = true;
            })

            const parties = this.members.map(a => a.party).filter(function (el) {
              return el != null;
            });
            this.filters.party['Kaikki'] = [...new Set(parties)];
          },
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
            if (email) {
              return email.replace("@", " (at) ")
            }
            return null;
          }
        },
        computed: {
          filteredMembers() {
            let temp_results = this.members;
            temp_results = temp_results.filter(result => {
              return result.first_name.toLowerCase().includes(this.search.toLowerCase()) || result.last_name.toLowerCase().includes(this.search.toLowerCase())
            })

            if(!this.active_checkboxes.includes('deputy_member')) {
              temp_results = temp_results.filter(result => result.role !== 'Varajäsen');
            }

            if(this.active_filters.party !== 'Kaikki') {
              temp_results = temp_results.filter(result => result.party === this.active_filters.party)
            }

            if(this.active_filters.order === 'A-Ö, nimen mukaan') {
              temp_results = temp_results.sort((a,b) => (a.last_name.toLowerCase().localeCompare(b.last_name.toLowerCase())));
            }

            if(this.active_filters.order === 'A-Ö, puolueen mukaan') {
              temp_results = temp_results.sort((a,b) => (a.party.toLowerCase().localeCompare(b.party.toLowerCase())));
            }

            return temp_results;
          },
          searchPlaceholder() {
            return Drupal.t('Syötä valtuutetun nimi');
          },
          searchLabel() {
            return Drupal.t('Hae valtuutettua');
          },
          hasDeputies() {
            return this.members.filter(result => result.role === 'Varajäsen').length > 0;
          }
        },
        mounted() {
          this.getJson();
        }
      });
    }
  }

}(jQuery, Drupal));
