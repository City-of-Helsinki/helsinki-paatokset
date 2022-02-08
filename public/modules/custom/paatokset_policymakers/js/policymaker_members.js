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
      <div class="policymaker-members__container">
        <div class="policymaker-members__filters">
          <div class="search-wrapper">
            <label for="search-input">{{ searchLabel }}</label>
            <input type="text" v-model="search" :placeholder="searchPlaceholder" id="search-input"/>
            <i class="hel-icon hel-icon--search"></i>
          </div>
          <div v-for="(filter, key) in filters" class="form-item">
            <div class="form-item__dropdown">
              <label :for="processId(filter.label)">
                {{ window.Drupal.t(filter.label) }}
                <select class="form-item__select" :id="processId(filter.label)" v-model="active_filters[key]">
                  <option :value="window.Drupal.t(Object.keys(filter)[0])">{{ window.Drupal.t(Object.keys(filter)[0]) }}</option>
                  <option v-for="value in Object.values(filter)[0]" v-bind:value="window.Drupal.t(value)">{{ window.Drupal.t(value) }}</option>
                </select>
                <i class="hel-icon hel-icon--angle-down"></i>
              </label>
            </div>
          </div>
          <div v-if="hasDeputies" class="filters-checkboxes">
              <li v-for="checkbox in checkboxes" class="form-item--checkbox__item">
                <label :for="Object.keys(checkbox)[0]">
                  <input :checked="active_checkboxes.includes(Object.keys(checkbox)[0])" :id="Object.keys(checkbox)[0]" name="checkbox" type="checkbox" @click="addToActiveCheckbox(Object.keys(checkbox)[0])">
                  <span>{{ window.Drupal.t(Object.values(checkbox)[0]) }}</span>
                </label>
              </li>
          </div>
        </div>
        <div class="policymaker-members__list">
          <a v-for="member in filteredMembers" class="member-row" :href="member.url">
            <div class="member-info">
              <h4 class="member-name">{{ member.first_name }} {{ member.last_name }}</h4>
              <div>
                <span class="member-role">{{ member.role }}</span>
                <span v-if="member.party" class="member-party"> {{ member.party }}</span>
              </div>
              <span v-if="member.email" class="member-email">{{ processEmail(member.email) }}</span>
              <template v-if="policymakerType !== 'Valtuusto'">
                <span v-if ="member.deputy_of" class="deputy-of">{{ deputyOf }}: {{ member.deputy_of }}</span>
              </template>
            </div>
            <div class="member-image">
              <img v-if="member.image_url" :src="member.image_url"/>
            </div>
          </a>
          <div v-if="filteredMembers.length === 0" class="no-results">
            <h4>{{ noResults }}</h4>
          </div>
        </div>
      </div>
      `
      const container = document.querySelector('.policymaker-members__block');
      const policymakerID = container.dataset.policymaker;
      const policymakerType = container.dataset.type;
      const dataURL = window.location.origin + '/fi/ahjo_api/org_composition/' + policymakerID;

      new Vue({
        el: '#policymaker-members-vue',
        template: markup,
        data: {
          members: [],
          isReady: false,
          search: '',
          filters: {
            party: { 'All': [], label : 'Filter by party' },
            order: { 'A-Ö, by name': ['A-Ö, by party'], label: 'Sorting'}
          },
          active_filters: {
            party: window.Drupal.t('All'),
            order: window.Drupal.t('A-Ö, by name')
          },
          checkboxes: {
            deputy_member: { deputy_member: 'Show deputies'}
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
              self.filters.party['All'] = [...new Set(parties.sort())];
            })
            .done(function( json ) {
              self.isReady = true;
            })

            const parties = this.members.map(a => a.party).filter(function (el) {
              return el != null;
            });
            this.filters.party['All'] = [...new Set(parties.sort())];
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
          },
          processId(string) {
            return string.replace(/\s/g, '-').toLowerCase()
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

            if(this.active_filters.party !== window.Drupal.t('All')) {
              temp_results = temp_results.filter(result => result.party === this.active_filters.party)
            }

            if(this.active_filters.order === window.Drupal.t('A-Ö, by name')) {
              temp_results = temp_results.sort((a,b) => (a.last_name.toLowerCase().localeCompare(b.last_name.toLowerCase())));
            }

            if(this.active_filters.order === window.Drupal.t('A-Ö, by party')) {
              temp_results = temp_results.sort((a,b) => {
                return (a?.party?.toLowerCase().localeCompare(b?.party?.toLowerCase())) || (a?.last_name.toLowerCase().localeCompare(b?.last_name.toLowerCase()))
              });
            }
            return temp_results;
          },
          searchPlaceholder() {
            return window.Drupal.t('Insert name');
          },
          searchLabel() {
            return window.Drupal.t('Search for member');
          },
          hasDeputies() {
            return this.members.filter(result => result.role === 'Varajäsen').length > 0;
          },
          deputyOf() {
            return window.Drupal.t('Personal deputy')
          },
          noResults() {
            return window.Drupal.t('No search results')
          },
          translations() {
            window.Drupal.t('Filter by party');
            window.Drupal.t('Sorting');
            window.Drupal.t('All');
            window.Drupal.t('A-Ö, by name');
            window.Drupal.t('A-Ö, by party');
            window.Drupal.t('Show deputies')
          },
          policymakerType() {
            return policymakerType;
          }
        },
        mounted() {
          this.getJson();
        }
      });
    }
  }

}(jQuery, Drupal));
