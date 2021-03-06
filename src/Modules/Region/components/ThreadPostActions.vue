<template>
  <div class="bootstrap">
    <!-- emoji buttons & selector -->
    <span class="emojis">
      <span
        v-for="(users, key) in reactionsWithUsers"
        :key="key"
      >
        <a
          v-b-tooltip.hover
          :title="concatUsers(users)"
          :class="['btn', 'btn-sm', (gaveIThisReaction(key) ? 'btn-primary' : 'btn-secondary')]"
          @click="toggleReaction(key)"
        >
          {{ users.length }}x <Emoji :name="key" />
        </a>
      </span>
      <b-dropdown
        ref="emojiSelector"
        v-b-tooltip.hover
        title="Eine Reaktion hinzufügen"
        text="+"
        class="emoji-dropdown"
        size="sm"
        no-caret
        right
      >
        <a
          v-for="(symbol, key) in emojis"
          :key="key"
          @click="giveEmoji(key)"
          class="btn"
        >
          <Emoji :name="key" />
        </a>
      </b-dropdown>
    </span>

    <span :class="{divider: true, textPprimary: true, mobile: isMobile }" />

    <!-- non emoji button -->
    <a
      @click="$emit('reply')"
      class="btn btn-sm btn-secondary"
    >
      {{ $i18n('button.answer') }}
    </a>
    <a
      v-if="mayDelete"
      v-b-tooltip.hover
      @click="$refs.confirmDelete.show()"
      title="Beitrag löschen"
      class="btn btn-sm btn-secondary"
    >
      <i class="fas fa-trash-alt" />
    </a>

    <!-- <a
      v-if="mayEdit"
      v-b-tooltip.hover
      title="Beitrag bearbeiten"
      class="btn btn-sm btn-secondary"
      @click="$emit('edit')">
      <i class="fas fa-pencil-alt" />
    </a> -->

    <!-- delete confirm modal -->
    <b-modal
      ref="confirmDelete"
      v-if="mayDelete"
      :title="$i18n('forum.delete_post')"
      :cancel-title="$i18n('button.abort')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      @ok="$emit('delete')"
      modal-class="bootstrap"
    >
      <p>{{ $i18n('really_delete') }}</p>
    </b-modal>
  </div>
</template>

<script>
import pickBy from 'lodash.pickby'

import { BDropdown, BModal, VBTooltip } from 'bootstrap-vue'

import Emoji from '@/components/Emoji'
import emojiList from '@/emojiList.json'
import { user } from '@/server-data'

export default {
  components: { BDropdown, Emoji, BModal },
  directives: { VBTooltip },
  props: {
    reactions: {
      type: Object,
      default: () => ({})
    },
    mayDelete: {
      type: Boolean,
      default: false
    },
    isMobile: {
      type: Boolean,
      default: false
    }
  },
  data () {
    return {
      emojis: emojiList
    }
  },
  computed: {
    reactionsWithUsers () {
      return pickBy(this.reactions, users => users.length > 0)
    }
  },
  methods: {
    toggleReaction (key, dontRemove = false) {
      if (this.gaveIThisReaction(key)) {
        if (!dontRemove) this.$emit('reactionRemove', key)
      } else {
        this.$emit('reactionAdd', key)
      }
    },
    giveEmoji (key) {
      this.$refs.emojiSelector.hide()
      this.toggleReaction(key, true)
    },
    gaveIThisReaction (key) {
      if (!this.reactions[key]) return false
      return !!this.reactions[key].find(r => r.id === user.id)
    },
    concatUsers (users) {
      const names = users.map(u => u.id === user.id ? 'Du' : u.name)
      if (names.length === 1) return names[0]

      return `${names.slice(0, names.length - 1).join(', ')} & ${names[names.length - 1]}`
    }
  }
}
</script>

<style lang="scss">
.emoji-dropdown > button {
    border-radius: 0.2rem !important;
}
.emoji-dropdown .dropdown-menu {
    padding: 10px;
    a.btn {
        padding: 0;
    }
    .emoji {
        padding: 0 0.3em;
    }
}
</style>

<style lang="scss" scoped>
.emojis {
    line-height: 2.2;
    > span > a {
        color: white !important;
        margin-left: 0.3em;
        padding: 0.05rem 0.5rem;
        span {
            font-size: 1.35em;
        }
    }
}
.divider {
    margin: 0 0.3em;
    opacity: 0.3;
    &::before {
        content: '|';
    }
}
.divider.mobile {
    &::before {
        content: '';
    }
    height: 5px;
    display: block;
}
</style>
