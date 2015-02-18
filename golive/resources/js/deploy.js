/* global Craft */
(function ($) {
  'use strict';

  var GoLiveComponent = function (options) {
    this.options = options;
    this.element = this.options.element;
    this.goliveButton =
      $(this.element).find('.btn.golive');
    this.progressBar =
      $(this.element).find('.golive-progressbar');
    this.progressBarInner =
      $(this.element).find('.progressbar-inner');
    this.progressBarText =
      $(this.element).find('.progressbar-text');
    this.taskRunnerRequest = null;
    this.taskHasStarted = false;
    this.tasks = [];

    this.addEventListeners();
    this.getTasks();
  };

  GoLiveComponent.prototype.addEventListeners = function() {
    var GoLiveComponent = this,
      element = GoLiveComponent.element;

    $(element).on('click', '.btn.golive', function (e) {
      e.preventDefault();

      if($(this).hasClass('disabled')) {
        return;
      }

      $(this).addClass('disabled');

      GoLiveComponent.progressBar.addClass('show');
      GoLiveComponent.setProgress(0, GoLiveComponent.tasks[0]);

      // This request will last as long as the task itself if gzip is enabled,
      // so just shut it down to free up the xhr slot
      GoLiveComponent.taskRunnerRequest =
        $.get(Craft.actionUrl + '/goLive/deploy/createTask');
      GoLiveComponent.monitorTask();
      GoLiveComponent.setProgress('pending', '');
    });
  };

  GoLiveComponent.prototype.monitorTask = function() {
    var GoLiveComponent = this;

    GoLiveComponent.taskInterval = setInterval(function () {
      $.post(Craft.actionUrl + '/tasks/getRunningTaskInfo')
      .then(function (data, status, xhr) {
        var task = data;

        if(typeof task.id === 'undefined') {
          GoLiveComponent.setProgress(100);
          $('.btn.task').removeClass('disabled');
          clearInterval(GoLiveComponent.taskInterval);

          return this;
        }

        if(task.description !== 'GoLive_Deploy') {
          GoLiveComponent.setProgress(
            'pending',
            'Waiting for other tasks to complete...'
          );

          return this;
        }

        GoLiveComponent.taskRunnerRequest.abort();

        var totalSteps = GoLiveComponent.tasks.length,
          taskIndex = Math.abs(Math.round(task.progress * totalSteps)) - 1,
          taskDescription = GoLiveComponent.tasks[taskIndex].message;

        task.progress = task.progress - (1 / totalSteps);
        GoLiveComponent.setProgress(task.progress * 100, taskDescription);
      });
    }, 1000);
  };

  GoLiveComponent.prototype.getTasks = function() {
    var GoLiveComponent = this;

    $.get(Craft.actionUrl + '/goLive/tasks/getTasks')
    .then(function (data) {
      GoLiveComponent.tasks = $.map(data, function (element) {
        return element;
      });
    });
  };

  GoLiveComponent.prototype.setProgress = function(
    progressAmount,
    progressDescription
  ) {
    var GoLiveComponent = this;

    if(progressAmount === 'pending') {
      GoLiveComponent.progressBarInner.css({
        width: '100%'
      });
      GoLiveComponent.progressBar.find('.progressbar').addClass('pending');
      GoLiveComponent.progressBarText.html(progressDescription);

      return;
    }

    if(progressAmount >= 100) {
      GoLiveComponent.resetProgress();
      return;
    }


    GoLiveComponent.progressBar.find('.progressbar').removeClass('pending');
    GoLiveComponent.progressBarInner.css({
      width: progressAmount + '%'
    });

    GoLiveComponent.progressBarText.html(progressDescription);
  };

  GoLiveComponent.prototype.resetProgress = function() {
    var GoLiveComponent = this;

    // Go to 100
    GoLiveComponent.progressBarInner.css({
      width: '100%'
    });
    GoLiveComponent.progressBarText.html('');

    // Let that sink in, then hide the bar
    setTimeout(function () {
      GoLiveComponent.progressBar.removeClass('show');
      GoLiveComponent.goliveButton.removeClass('disabled');
    }, 400);

    // After it's hidden, reset the progress for another run
    setTimeout(function () {
      GoLiveComponent.progressBarInner.css({
        width: '0%'
      });
    }, 600);
  };

  $('.golive-component').each(function () {
    new GoLiveComponent({
      element: this
    });
  });
})(jQuery);
