/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* global jeedom, eqType, addCmdToTable, modifyWithoutSave */

'use strict'

function printEqLogic(_eqLogic) {
  $('.eqLogicAttr').setValues(_eqLogic, '.eqLogicAttr')

  $('.eqLogicAttr[data-l1key=configuration]').each(function () {
    var key = $(this).attr('data-l2key')
    if (isset(_eqLogic.configuration) && isset(_eqLogic.configuration[key])) {
      $(this).value(_eqLogic.configuration[key])
    } else {
      $(this).value('')
    }
  })

  $('.eqLogicAttr[data-l1key=category]').each(function () {
    var key = $(this).attr('data-l2key')
    if (isset(_eqLogic.category) && isset(_eqLogic.category[key]) && _eqLogic.category[key] === '1') {
      $(this).prop('checked', true)
    } else {
      $(this).prop('checked', false)
    }
  })

  $('.cmd').remove()
  if (isset(_eqLogic.cmd)) {
    for (var i in _eqLogic.cmd) {
      if (!_eqLogic.cmd.hasOwnProperty(i)) {
        continue
      }
      addCmdToTable(_eqLogic.cmd[i])
    }
  }
  if (typeof modifyWithoutSave !== 'undefined') {
    modifyWithoutSave = 0
  }
}

function loadEqLogic(_eqLogicId) {
  jeedom.eqLogic.load({
    id: _eqLogicId,
    type: eqType,
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' })
    },
    success: function (data) {
      printEqLogic(data)
      $('.eqLogic').show()
      $('.eqLogicThumbnailDisplay').hide()
      $('.eqLogicAction[data-action=remove]').removeClass('disabled')
      $('.eqLogicAction[data-action=save]').removeClass('disabled')
      $('.eqLogicDisplayCard').removeClass('active')
      $('.eqLogicDisplayCard[data-eqLogic_id=' + data.id + ']').addClass('active')
    }
  })
}

$(function () {
  $('#bt_resetSearch').on('click', function () {
    $('#in_searchEqlogic').val('')
    $('.eqLogicThumbnailContainer .eqLogicDisplayCard').show()
  })

  $('#in_searchEqlogic').on('keyup', function () {
    var search = $(this).val().toLowerCase()
    $('.eqLogicThumbnailContainer .eqLogicDisplayCard').each(function () {
      var text = $(this).find('.name').text().toLowerCase()
      if (text.indexOf(search) === -1) {
        $(this).hide()
      } else {
        $(this).show()
      }
    })
  })

  $('.eqLogicThumbnailContainer').on('click', '.eqLogicDisplayCard', function () {
    var eqLogicId = $(this).data('eqlogic_id')
    if (!eqLogicId) {
      return
    }
    loadEqLogic(eqLogicId)
  })

  $('.eqLogicAction[data-action=returnToThumbnailDisplay]').on('click', function () {
    $('.eqLogic').hide()
    $('.eqLogicThumbnailDisplay').show()
    $('.eqLogicDisplayCard').removeClass('active')
    $('.eqLogicAction[data-action=remove]').addClass('disabled')
    $('.eqLogicAction[data-action=save]').addClass('disabled')
  })

  $('.eqLogicAction[data-action=add]').on('click', function () {
    jeedom.eqLogic.add({
      type: eqType,
      error: function (error) {
        $('#div_alert').showAlert({ message: error.message, level: 'danger' })
      },
      success: function (eqLogic) {
        if (isset(eqLogic) && isset(eqLogic.id)) {
          loadEqLogic(eqLogic.id)
        }
      }
    })
  })
})
