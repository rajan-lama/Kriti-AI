/**
 * Kriti AI admin UI.
 *
 * Vanilla JS powering the Generate, Queue, Media and Dashboard screens.
 */
(function () {
  "use strict";

  var __ =
    window.wp && window.wp.i18n && window.wp.i18n.__
      ? window.wp.i18n.__
      : function (text) {
          return text;
        };

  var api = {
    post: function (action, data) {
      var body = new URLSearchParams();
      body.append("action", action);
      body.append("nonce", KritiAI.nonce);
      Object.keys(data).forEach(function (key) {
        body.append(key, data[key]);
      });

      return fetch(KritiAI.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (json) {
          if (!json || !json.success) {
            var msg =
              json && json.data && json.data.message
                ? json.data.message
                : __("Request failed", "kriti-ai");
            throw new Error(msg);
          }
          return json.data;
        });
    },
    esc: function (value) {
      var div = document.createElement("div");
      div.textContent = value == null ? "" : String(value);
      return div.innerHTML;
    },
    fmt: function (value) {
      return Number(value).toLocaleString();
    },
  };

  function statusBadge(status) {
    var map = {
      pending: "ra-status kriti-ai-pending",
      processing: "ra-status kriti-ai-processing",
      completed: "ra-status kriti-ai-completed",
      failed: "ra-status kriti-ai-failed",
      cancelled: "ra-status kriti-ai-cancelled",
      draft: "ra-status kriti-ai-pending",
      published: "ra-status kriti-ai-completed",
    };
    return (
      '<span class="' +
      (map[status] || "ra-status") +
      '">' +
      api.esc(status) +
      "</span>"
    );
  }

  function humanTime(mysqlDate) {
    if (!mysqlDate) {
      return "—";
    }
    var date = new Date(String(mysqlDate).replace(" ", "T") + "Z");
    return date.toLocaleString();
  }

  /* -------------------------------------------------- Generate */

  function initGenerate() {
    var form = document.getElementById("kriti-ai-generate-form");
    if (!form) {
      return;
    }

    var typeField = document.getElementById("kriti-ai-gen-type");
    var tabs = Array.prototype.slice.call(
      document.querySelectorAll(".kriti-ai-tab"),
    );
    var providerSelects = Array.prototype.slice.call(
      document.querySelectorAll(".kriti-ai-provider-select"),
    );
    var contentTypeInputs = Array.prototype.slice.call(
      document.querySelectorAll('input[name="content_type"]'),
    );
    var promptSelect = document.getElementById("kriti-ai-prompt-select");
    var modelInput = document.getElementById("kriti-ai-model");
    var useProviderDefaultBtn = document.getElementById(
      "kriti-ai-use-provider-default",
    );
    var modelSource = document.getElementById("kriti-ai-model-source");
    var modelHelp = document.getElementById("kriti-ai-model-help");
    var temperatureInput = document.getElementById("kriti-ai-temperature");
    var temperatureRange = document.getElementById(
      "kriti-ai-temperature-range",
    );
    var temperatureValue = document.getElementById(
      "kriti-ai-temperature-value",
    );
    var maxTokensInput = document.getElementById("kriti-ai-max-tokens");
    var maxTokensRange = document.getElementById("kriti-ai-max-tokens-range");
    var maxTokensValue = document.getElementById("kriti-ai-max-tokens-value");
    var topPInput = document.getElementById("kriti-ai-top-p");
    var topPRange = document.getElementById("kriti-ai-top-p-range");
    var topPValue = document.getElementById("kriti-ai-top-p-value");
    var systemInstructions = document.getElementById("system_instructions");
    var resetSystemInstructions = document.getElementById(
      "kriti-ai-reset-system-instructions",
    );
    var promptCanvasTitle = document.getElementById(
      "kriti-ai-prompt-canvas-title",
    );
    var promptCanvasHelp = document.getElementById(
      "kriti-ai-prompt-canvas-help",
    );
    var contentPromptLabel = document.querySelector(
      'label[for="kriti-ai-prompt"]',
    );
    var contentPrompt = document.getElementById("kriti-ai-prompt");
    var contentPromptTip =
      contentPrompt && contentPrompt.parentElement
        ? contentPrompt.parentElement.querySelector(".description")
        : null;
    var resultPanel = document.getElementById("kriti-ai-result-panel");
    var resultBox = document.getElementById("kriti-ai-result");
    var genBtn = document.getElementById("kriti-ai-generate-btn");
    var genBtnLabel = document.getElementById("kriti-ai-generate-btn-label");
    var genBtnDefaultHtml = genBtn ? genBtn.innerHTML : "";
    var recentActivity = document.getElementById("kriti-ai-recent-activity");
    var pollTimer = null;

    var promptCanvasMetaByType = {
      article: {
        title: __("Article Prompt", "kriti-ai"),
        help: __(
          "Describe the article topic, target reader, structure, and tone.",
          "kriti-ai",
        ),
        label: __("Article instructions", "kriti-ai"),
        placeholder: __(
          "Write a long-form article about [topic]. Include an SEO title, intro, 4-6 sections with headings, key takeaways, and a concise conclusion.",
          "kriti-ai",
        ),
        tip: __(
          "Tip: include audience, length, tone, and required keywords for better outputs.",
          "kriti-ai",
        ),
      },
      image: {
        title: __("Image Prompt", "kriti-ai"),
        help: __(
          "Define visual details, lighting, style, composition, and mood.",
          "kriti-ai",
        ),
      },
      audio: {
        title: __("Audio Prompt", "kriti-ai"),
        help: __(
          "Create spoken content with clear pacing, emphasis, and delivery style.",
          "kriti-ai",
        ),
      },
      video: {
        title: __("Video Prompt", "kriti-ai"),
        help: __(
          "Define scenes, visuals, mood, and pacing for better video outputs.",
          "kriti-ai",
        ),
      },
    };

    function tabPanels() {
      return Array.prototype.slice.call(
        document.querySelectorAll("[data-gen-for]"),
      );
    }

    function mapContentTypeToGenerateType(contentType) {
      if (
        contentType === "image" ||
        contentType === "audio" ||
        contentType === "video"
      ) {
        return contentType;
      }
      return "content";
    }

    function getSelectedContentType() {
      var checked = contentTypeInputs.filter(function (input) {
        return input.checked;
      });

      if (!checked.length) {
        return "article";
      }

      return checked[checked.length - 1].value;
    }

    function setContentTypeInput(type) {
      if (!contentTypeInputs.length) {
        return;
      }

      var desired =
        type === "image" || type === "audio" || type === "video"
          ? type
          : "article";
      var target = contentTypeInputs.filter(function (input) {
        return input.value === desired;
      })[0];

      if (!target) {
        return;
      }

      if (target.type === "checkbox") {
        contentTypeInputs.forEach(function (input) {
          input.checked = false;
        });
      }

      target.checked = true;
    }

    function syncFromContentTypeInput(changedInput) {
      var selectedContentType =
        changedInput && changedInput.checked
          ? changedInput.value
          : getSelectedContentType();

      setTab(mapContentTypeToGenerateType(selectedContentType));
      updatePromptCanvasMeta(selectedContentType);
    }

    function updatePromptCanvasMeta(contentType) {
      var meta =
        promptCanvasMetaByType[contentType] || promptCanvasMetaByType.article;

      if (promptCanvasTitle && meta.title) {
        promptCanvasTitle.textContent = meta.title;
      }

      if (promptCanvasHelp && meta.help) {
        promptCanvasHelp.textContent = meta.help;
      }

      if (contentType === "article") {
        if (contentPromptLabel && meta.label) {
          contentPromptLabel.textContent = meta.label;
        }
        if (contentPrompt && meta.placeholder) {
          contentPrompt.placeholder = meta.placeholder;
        }
        if (contentPromptTip && meta.tip) {
          contentPromptTip.textContent = meta.tip;
        }
      }
    }

    function setTab(type) {
      typeField.value = type;

      tabs.forEach(function (tab) {
        tab.classList.toggle(
          "is-active",
          tab.getAttribute("data-tab") === type,
        );
      });

      tabPanels().forEach(function (panel) {
        var belongs = panel.getAttribute("data-gen-for") === type;
        panel.hidden = !belongs;

        Array.prototype.slice
          .call(panel.querySelectorAll("input, select, textarea"))
          .forEach(function (field) {
            field.disabled = !belongs;
          });
      });

      providerSelects.forEach(function (select) {
        var parent = select.closest("[data-gen-for]");
        var belongs = parent && parent.getAttribute("data-gen-for") === type;
        select.disabled = !belongs;
        if (belongs && !select.value) {
          select.selectedIndex = 0;
        }
      });

      refreshModels(true);
      setGenerateButtonState(false);
    }

    function generateButtonLabel(type) {
      var map = {
        content: __("Generate Content", "kriti-ai"),
        image: __("Generate Image", "kriti-ai"),
        audio: __("Generate Audio", "kriti-ai"),
        video: __("Generate Video", "kriti-ai"),
      };

      return map[type] || map.content;
    }

    function setGenerateButtonState(workingText) {
      if (!genBtn) {
        return;
      }

      if (genBtnLabel) {
        genBtnLabel.textContent =
          workingText || generateButtonLabel(typeField.value);
      } else if (workingText) {
        genBtn.textContent = workingText;
      } else {
        genBtn.innerHTML = genBtnDefaultHtml;
      }
    }

    function activeProviderSelect() {
      var active = providerSelects.filter(function (s) {
        return !s.disabled;
      })[0];

      return active || null;
    }

    function refreshModels(clearInvalidModel) {
      var active = activeProviderSelect();
      var slug = active ? active.value : "";
      var models = (KritiAI.models && KritiAI.models[slug]) || [];

      if (!modelInput) {
        return;
      }

      if (
        clearInvalidModel &&
        modelInput.value &&
        models.length &&
        models.indexOf(modelInput.value) === -1
      ) {
        modelInput.value = "";
      }

      setModelOptions(models, modelInput.value);
      updateModelUX();
    }

    function setModelOptions(models, selectedModel) {
      if (!modelInput) {
        return;
      }

      var value = selectedModel || "";
      var options = [
        '<option value="">' +
          api.esc(__("Provider default", "kriti-ai")) +
          "</option>",
      ];

      models.forEach(function (model) {
        options.push(
          '<option value="' +
            api.esc(model) +
            '">' +
            api.esc(model) +
            "</option>",
        );
      });

      if (value && models.indexOf(value) === -1) {
        options.push(
          '<option value="' +
            api.esc(value) +
            '">' +
            api.esc(value) +
            "</option>",
        );
      }

      modelInput.innerHTML = options.join("");
      modelInput.value = value;
    }

    function setModelValue(value) {
      var active = activeProviderSelect();
      var slug = active ? active.value : "";
      var models = (KritiAI.models && KritiAI.models[slug]) || [];

      setModelOptions(models, value || "");
      updateModelUX();
    }

    function updateModelUX() {
      if (!modelInput) {
        return;
      }

      var activeProvider = providerSelects.filter(function (s) {
        return !s.disabled;
      })[0];
      var providerLabel = activeProvider ? activeProvider.value : "provider";
      var modelValue = (modelInput.value || "").trim();

      if (!modelValue) {
        if (modelSource) {
          modelSource.textContent = __("Provider default", "kriti-ai");
        }
        if (modelHelp) {
          modelHelp.textContent =
            __("Using default model from", "kriti-ai") +
            " " +
            providerLabel +
            ".";
        }
      } else {
        if (modelSource) {
          modelSource.textContent = __("Model override", "kriti-ai");
        }
        if (modelHelp) {
          modelHelp.textContent =
            __("Using", "kriti-ai") +
            " " +
            modelValue +
            " " +
            __("with", "kriti-ai") +
            " " +
            providerLabel +
            ".";
        }
      }

      if (useProviderDefaultBtn) {
        useProviderDefaultBtn.disabled = !modelValue;
      }
    }

    function syncParameterControls() {
      if (temperatureInput && temperatureRange) {
        temperatureRange.value = temperatureInput.value;
        if (temperatureValue) {
          temperatureValue.textContent = Number(temperatureInput.value).toFixed(
            1,
          );
        }
      }

      if (maxTokensInput && maxTokensRange) {
        maxTokensRange.value = maxTokensInput.value;
        if (maxTokensValue) {
          maxTokensValue.textContent = api.fmt(maxTokensInput.value);
        }
      }

      if (topPInput && topPRange) {
        topPRange.value = topPInput.value;
        if (topPValue) {
          topPValue.textContent = Number(topPInput.value).toFixed(2);
        }
      }
    }

    function bindNumberWithRange(numberInput, rangeInput, valueEl, formatter) {
      if (!numberInput || !rangeInput) {
        return;
      }

      function fromNumber() {
        rangeInput.value = numberInput.value;
        if (valueEl) {
          valueEl.textContent = formatter(numberInput.value);
        }
      }

      function fromRange() {
        numberInput.value = rangeInput.value;
        if (valueEl) {
          valueEl.textContent = formatter(rangeInput.value);
        }
      }

      numberInput.addEventListener("input", fromNumber);
      numberInput.addEventListener("change", fromNumber);
      rangeInput.addEventListener("input", fromRange);
      rangeInput.addEventListener("change", fromRange);
    }

    function activityTypeLabel(jobType) {
      var map = {
        text: __("Article", "kriti-ai"),
        content: __("Article", "kriti-ai"),
        image: __("Image", "kriti-ai"),
        audio: __("Audio", "kriti-ai"),
        video: __("Video", "kriti-ai"),
      };
      return map[jobType] || __("Generation", "kriti-ai");
    }

    function activityTitle(job) {
      var result = job.result || {};
      if (result.title) {
        return String(result.title);
      }
      if (job.title) {
        return String(job.title);
      }
      if (result.prompt_title) {
        return String(result.prompt_title);
      }
      if (result.input_title) {
        return String(result.input_title);
      }
      if (job.status === "failed" && job.error) {
        return __("Generation failed", "kriti-ai");
      }
      return (
        __("Generation", "kriti-ai") + " " + activityTypeLabel(job.job_type)
      );
    }

    function activityTime(createdAt) {
      if (!createdAt) {
        return "—";
      }
      var created = new Date(String(createdAt).replace(" ", "T") + "Z");
      var diff = Math.max(0, Date.now() - created.getTime());
      var mins = Math.floor(diff / 60000);
      if (mins < 1) {
        return __("just now", "kriti-ai");
      }
      if (mins < 60) {
        return mins + "m ago";
      }
      var hours = Math.floor(mins / 60);
      if (hours < 24) {
        return hours + "h ago";
      }
      var days = Math.floor(hours / 24);
      return days + "d ago";
    }

    function activityBadgeClass(jobType) {
      if (jobType === "audio") {
        return "bg-kriti-ai-surface-container-high text-kriti-ai-on-surface-variant";
      }
      if (jobType === "video") {
        return "bg-amber-100 text-amber-800";
      }
      return "bg-kriti-ai-indigo-wash text-kriti-ai-primary";
    }

    function renderRecentActivity(jobs) {
      if (!recentActivity) {
        return;
      }

      if (!jobs || !jobs.length) {
        recentActivity.innerHTML =
          '<div class="border border-kriti-ai-outline-variant/30 rounded p-3 bg-kriti-ai-surface"><p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray">' +
          api.esc(__("No recent generations yet.", "kriti-ai")) +
          "</p></div>";
        return;
      }

      recentActivity.innerHTML = jobs
        .slice(0, 3)
        .map(function (job) {
          var typeLabel = activityTypeLabel(job.job_type);
          var timeText = activityTime(job.created_at);
          var title = activityTitle(job);
          var titleEsc = api.esc(title);
          var status = api.esc(job.status || "pending");
          var badgeClass = activityBadgeClass(job.job_type);

          return (
            '<div class="border border-kriti-ai-outline-variant/30 rounded p-3 bg-kriti-ai-surface hover:border-kriti-ai-primary/50 transition-colors">' +
            '<div class="flex justify-between items-start mb-1">' +
            '<span class="' +
            badgeClass +
            ' px-2 py-0.5 rounded text-xs font-kriti-ai-label-md">' +
            api.esc(typeLabel) +
            "</span>" +
            '<span class="text-kriti-ai-slate-gray text-xs">' +
            api.esc(timeText) +
            "</span>" +
            "</div>" +
            '<p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-on-surface line-clamp-2">' +
            titleEsc +
            "</p>" +
            '<p class="text-xs text-kriti-ai-slate-gray mt-1">' +
            api.esc(__("Status:", "kriti-ai")) +
            " " +
            status +
            "</p>" +
            "</div>"
          );
        })
        .join("");
    }

    function loadRecentActivity() {
      if (!recentActivity) {
        return;
      }

      api
        .post("kriti_ai_jobs_recent", { limit: 10 })
        .then(function (jobs) {
          renderRecentActivity(jobs || []);
        })
        .catch(function () {
          recentActivity.innerHTML =
            '<div class="border border-kriti-ai-outline-variant/30 rounded p-3 bg-kriti-ai-surface"><p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray">' +
            api.esc(__("Unable to load recent activity.", "kriti-ai")) +
            "</p></div>";
        });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        setTab(tab.getAttribute("data-tab"));
      });
    });

    contentTypeInputs.forEach(function (input) {
      input.addEventListener("change", function () {
        syncFromContentTypeInput(input);
      });
    });

    providerSelects.forEach(function (select) {
      select.addEventListener("change", function () {
        if (modelInput) {
          modelInput.value = "";
        }
        refreshModels(false);
      });
    });

    if (modelInput) {
      modelInput.addEventListener("change", updateModelUX);
    }

    if (useProviderDefaultBtn && modelInput) {
      useProviderDefaultBtn.addEventListener("click", function () {
        modelInput.value = "";
        updateModelUX();
      });
    }

    bindNumberWithRange(
      temperatureInput,
      temperatureRange,
      temperatureValue,
      function (value) {
        return Number(value).toFixed(1);
      },
    );

    bindNumberWithRange(
      maxTokensInput,
      maxTokensRange,
      maxTokensValue,
      function (value) {
        return api.fmt(value);
      },
    );

    bindNumberWithRange(topPInput, topPRange, topPValue, function (value) {
      return Number(value).toFixed(2);
    });

    if (resetSystemInstructions && systemInstructions) {
      resetSystemInstructions.addEventListener("click", function () {
        systemInstructions.value = "";
        systemInstructions.focus();
      });
    }

    if (promptSelect) {
      promptSelect.addEventListener("change", function () {
        var id = promptSelect.value;
        if (!id) {
          return;
        }

        api
          .post("kriti_ai_load_prompt", { prompt_id: id })
          .then(function (prompt) {
            var title = document.getElementById("kriti-ai-title");
            var type = prompt.item_type || "content";

            if (title) {
              title.value = prompt.title || "";
            }
            document.getElementById("kriti-ai-temperature").value =
              prompt.temperature || KritiAI.defaults.temperature;
            document.getElementById("kriti-ai-max-tokens").value =
              prompt.max_tokens || KritiAI.defaults.max_tokens;
            if (topPInput) {
              topPInput.value =
                prompt.top_p !== undefined && prompt.top_p !== ""
                  ? prompt.top_p
                  : 1;
            }
            syncParameterControls();

            if (type === "audio") {
              document.getElementById("kriti-ai-text").value =
                prompt.content || "";
            } else if (type === "video") {
              document.getElementById("kriti-ai-video-prompt").value =
                prompt.content || "";
            } else if (type === "image") {
              var imgEl = document.getElementById("kriti-ai-image-prompt");
              if (imgEl) {
                imgEl.value = prompt.content || "";
              }
            } else {
              document.getElementById("kriti-ai-prompt").value =
                prompt.content || "";
            }

            setContentTypeInput(type);
            setTab(type);
            updatePromptCanvasMeta(type);

            if (prompt.provider) {
              var active = providerSelects.filter(function (s) {
                return !s.disabled;
              })[0];
              if (active) {
                active.value = prompt.provider;
              }
              refreshModels(false);
              setModelValue(prompt.model || "");
            } else if (modelInput) {
              setModelValue(prompt.model || "");
            }

            updateModelUX();
          })
          .catch(function (err) {
            window.alert(err.message);
          });
      });
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      var type = typeField.value;
      var provider = providerSelects.filter(function (s) {
        return !s.disabled;
      })[0];

      var data = {};
      var fd = new FormData(form);
      fd.forEach(function (value, key) {
        data[key] = value;
      });

      if (type === "video" && data.video_prompt) {
        data.prompt = data.video_prompt;
      } else if (type === "image" && data.image_prompt) {
        data.prompt = data.image_prompt;
      }

      data.provider = provider ? provider.value : "";
      data.nonce = KritiAI.nonce;

      genBtn.disabled = true;
      setGenerateButtonState(__("Starting…", "kriti-ai"));

      if (resultPanel) {
        resultPanel.hidden = false;
        resultBox.innerHTML = renderProgress({
          progress: 5,
          status: "pending",
          job_type: type,
        });
      }

      api
        .post("kriti_ai_generate", data)
        .then(function (res) {
          pollJob(res.job_id, res.type, 0);
        })
        .catch(function (err) {
          genBtn.disabled = false;
          setGenerateButtonState(false);
          if (resultPanel) {
            resultPanel.hidden = false;
            resultBox.innerHTML =
              '<div class="notice notice-error"><p>' +
              api.esc(err.message) +
              "</p></div>";
          }
        });
    });
    function renderTextResult(result) {
      var html = "";
      if (result.post_id) {
        var articleUrl = result.view_url || result.edit_url;

        html +=
          '<div class="flex-shrink-0"><a class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors" target="_blank" rel="noopener" href="' +
          api.esc(articleUrl) +
          '">' +
          api.esc(__("View Article", "kriti-ai")) +
          "</a></div>";
      }
      if (result.title) {
        html += "<h3>" + api.esc(result.title) + "</h3>";
      }
      if (result.content || result.body) {
        html +=
          '<div class="kriti-ai-result-body">' +
          (result.body || result.content) +
          "</div>";
      }
      return html;
    }

    function renderProgress(job) {
      var rawProgress = job && job.progress ? Number(job.progress) : 0;
      var progress = Math.max(5, Math.min(99, rawProgress || 5));
      var status = job && job.status ? String(job.status) : "pending";
      var typeLabel = activityTypeLabel(
        job && job.job_type ? job.job_type : typeField.value,
      );

      return (
        '<div class="flex-1">' +
        '<div class="flex items-center gap-3 mb-2">' +
        '<div class="flex-shrink-0">' +
        '<span class="spinner is-active" style="float:none;margin:0"></span>' +
        "</div>" +
        '<p class="text-sm font-medium text-slate-800">' +
        api.esc(
          typeLabel + " " + __("generation is in progress...", "kriti-ai"),
        ) +
        "</p>" +
        "</div>" +
        '<div class="w-full bg-slate-100 rounded-full h-2 relative overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' +
        api.esc(progress) +
        '">' +
        '<div class="bg-indigo-600 h-full rounded-full transition-all duration-500" style="width: ' +
        api.esc(progress) +
        '%"></div>' +
        "</div>" +
        '<div class="flex justify-between mt-1">' +
        '<span class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">' +
        api.esc(status) +
        "</span>" +
        '<span class="text-[10px] text-indigo-600 font-bold">' +
        api.esc(progress) +
        "%</span>" +
        "</div>" +
        "</div>"
      );
    }

    function renderMediaResult(result) {
      var html =
        '<p><a class="button" href="' +
        api.esc(result.url) +
        '" target="_blank" rel="noopener">' +
        api.esc(__("View file", "kriti-ai")) +
        "</a></p>";
      if (result.mime && result.mime.indexOf("audio") === 0) {
        html += '<audio controls src="' + api.esc(result.url) + '"></audio>';
      } else if (result.mime && result.mime.indexOf("video") === 0) {
        html +=
          '<video controls src="' +
          api.esc(result.url) +
          '" style="max-width:640px"></video>';
      } else if (result.mime && result.mime.indexOf("image") === 0) {
        html +=
          '<p><img src="' +
          api.esc(result.url) +
          '" style="max-width:320px" /></p>';
      }
      html +=
        '<p class="description">' +
        api.esc(
          __("Media ID", "kriti-ai") +
            " " +
            (result.post_id || result.attachment_id),
        ) +
        "</p>";
      return html;
    }

    function renderResult(type, result) {
      if (!result) {
        return (
          "<p>" +
          api.esc(__("Completed with no result payload.", "kriti-ai")) +
          "</p>"
        );
      }
      if (type === "content" || type === "text") {
        return renderTextResult(result);
      }
      return renderMediaResult(result);
    }

    function pollJob(jobId, type, attempt) {
      genBtn.disabled = true;
      setGenerateButtonState(__("Working…", "kriti-ai"));

      api
        .post("kriti_ai_job_status", { job_id: jobId })
        .then(function (job) {
          if (job.status === "completed") {
            genBtn.disabled = false;
            setGenerateButtonState(false);
            loadRecentActivity();
            if (resultPanel) {
              resultPanel.hidden = false;
              resultBox.innerHTML =
                '<div class="notice notice-success"><p>' +
                api.esc(__("Generation complete.", "kriti-ai")) +
                "</p></div>" +
                renderResult(type, job.result);
            }
            return;
          }

          if (resultPanel) {
            resultPanel.hidden = false;
            resultBox.innerHTML = renderProgress(job);
          }

          if (job.status === "failed" || job.status === "cancelled") {
            genBtn.disabled = false;
            setGenerateButtonState(false);
            loadRecentActivity();
            if (resultPanel) {
              resultPanel.hidden = false;
              resultBox.innerHTML =
                '<div class="notice notice-error"><p>' +
                api.esc(job.error || job.status) +
                "</p></div>";
            }
            return;
          }

          var delay = Math.min(30000, 4000 + attempt * 2000);
          window.setTimeout(function () {
            pollJob(jobId, type, attempt + 1);
          }, delay);
        })
        .catch(function (err) {
          genBtn.disabled = false;
          setGenerateButtonState(false);
          if (resultPanel) {
            resultPanel.hidden = false;
            resultBox.innerHTML =
              '<div class="notice notice-error"><p>' +
              api.esc(err.message) +
              "</p></div>";
          }
        });
    }

    if (contentTypeInputs.length) {
      syncFromContentTypeInput();
    } else {
      setTab("content");
    }

    syncParameterControls();

    updateModelUX();
    loadRecentActivity();
  }

  /* -------------------------------------------------- Queue */

  function initQueue() {
    var table = document.getElementById("kriti-ai-queue-table");
    if (!table) {
      return;
    }

    var tbody = table.querySelector("tbody");
    var statusEl = document.querySelector(".kriti-ai-live-status");
    var refreshBtn = document.getElementById("kriti-ai-refresh-queue");

    function render(jobs) {
      if (!jobs.length) {
        tbody.innerHTML =
          '<tr><td colspan="9">' +
          api.esc(__("No jobs yet.", "kriti-ai")) +
          "</td></tr>";
        return;
      }

      tbody.innerHTML = jobs
        .map(function (job) {
          var result = job.result || {};
          var detail = "";
          var action = "";

          if (job.status === "failed" && job.error) {
            detail =
              '<span class="kriti-ai-error-text">' +
              api.esc(job.error) +
              "</span>";
          } else if (job.status === "completed") {
            if (result.url) {
              detail =
                '<a href="' +
                api.esc(result.url) +
                '" target="_blank" rel="noopener">' +
                api.esc(result.title || __("View", "kriti-ai")) +
                "</a>";
            } else if (result.post_id) {
              detail =
                '<a href="' +
                api.esc(result.edit_url || "#") +
                '" target="_blank" rel="noopener">' +
                api.esc(__("Draft post #", "kriti-ai")) +
                result.post_id +
                "</a>";
            }
          }

          if (job.status === "pending" || job.status === "processing") {
            action =
              '<button type="button" class="button kriti-ai-cancel-job" data-id="' +
              job.id +
              '">' +
              api.esc(__("Cancel", "kriti-ai")) +
              "</button>";
          }

          return (
            "<tr>" +
            "<td>" +
            job.id +
            "</td>" +
            "<td>" +
            api.esc(job.job_type) +
            "</td>" +
            "<td>" +
            api.esc(job.provider) +
            "</td>" +
            "<td>" +
            api.esc(job.model || "—") +
            "</td>" +
            "<td>" +
            statusBadge(job.status) +
            "</td>" +
            "<td>" +
            job.progress +
            "%</td>" +
            "<td>" +
            api.esc(humanTime(job.created_at)) +
            "</td>" +
            "<td>" +
            detail +
            "</td>" +
            "<td>" +
            action +
            "</td>" +
            "</tr>"
          );
        })
        .join("");
    }

    function refresh() {
      if (statusEl) {
        statusEl.textContent = __("Refreshing…", "kriti-ai");
      }
      api
        .post("kriti_ai_jobs_recent", { limit: 25 })
        .then(function (jobs) {
          render(jobs);
          if (statusEl) {
            statusEl.textContent =
              __("Updated", "kriti-ai") + " " + new Date().toLocaleTimeString();
          }
        })
        .catch(function (err) {
          if (statusEl) {
            statusEl.textContent = err.message;
          }
        });
    }

    refreshBtn.addEventListener("click", refresh);

    tbody.addEventListener("click", function (event) {
      var btn = event.target.closest(".kriti-ai-cancel-job");
      if (!btn) {
        return;
      }

      api
        .post("kriti_ai_cancel_job", { job_id: btn.getAttribute("data-id") })
        .then(function () {
          refresh();
        })
        .catch(function (err) {
          window.alert(err.message);
        });
    });

    refresh();
    window.setInterval(refresh, 15000);
  }

  /* -------------------------------------------------- Media */

  function initMedia() {
    var table = document.querySelector(
      ".post-type-kriti_ai_media table.wp-list-table",
    );
    if (!table) {
      return;
    }

    var tbody = table.querySelector("tbody");

    tbody.addEventListener("click", function (event) {
      var btn = event.target.closest(
        ".kriti-ai-publish, .kriti-ai-unpublish, .kriti-ai-delete-media",
      );
      if (!btn) {
        return;
      }

      event.preventDefault();

      var actionName = "unpublish";

      if (btn.classList.contains("kriti-ai-publish")) {
        actionName = "publish";
      } else if (btn.classList.contains("kriti-ai-delete-media")) {
        actionName = "delete";
      }

      api
        .post("kriti_ai_publish_item", {
          item_id: btn.getAttribute("data-id"),
          action_name: actionName,
        })
        .then(function () {
          window.location.reload();
        })
        .catch(function (err) {
          window.alert(err.message);
        });
    });
  }

  /* -------------------------------------------------- Dashboard */

  function initDashboard() {
    var cards = document.getElementById("kriti-ai-metric-cards");
    if (!cards) {
      return;
    }

    var daysSelect = document.getElementById("kriti-ai-days");
    var refreshBtn = document.getElementById("kriti-ai-refresh-dashboard");
    var chartBox = document.getElementById("kriti-ai-chart");
    var draftBox = document.getElementById("kriti-ai-draft-published");
    var providerTable = document.getElementById("kriti-ai-provider-table");
    var volumeDays = document.getElementById("kriti-ai-volume-days");
    var recentBody = document.getElementById("kriti-ai-dashboard-recent-body");
    var connectedModels = document.getElementById("kriti-ai-connected-models");
    var apiUsageBar = document.getElementById("kriti-ai-api-usage-bar");
    var apiUsageFill = document.getElementById("kriti-ai-api-usage-fill");
    var apiUsageText = document.getElementById("kriti-ai-api-usage-text");

    function dashboardActivityType(jobType) {
      var map = {
        text: ["article", __("Article", "kriti-ai")],
        content: ["article", __("Article", "kriti-ai")],
        image: ["image", __("Image", "kriti-ai")],
        audio: ["audio_file", __("Audio", "kriti-ai")],
        video: ["movie", __("Video", "kriti-ai")],
      };

      return map[jobType] || ["auto_awesome", __("Generation", "kriti-ai")];
    }

    function dashboardActivityTitle(job) {
      var result = job.result || {};

      if (result.title) {
        return String(result.title);
      }
      if (job.title) {
        return String(job.title);
      }
      if (result.prompt_title) {
        return String(result.prompt_title);
      }
      if (result.input_title) {
        return String(result.input_title);
      }
      if (job.status === "failed" && job.error) {
        return __("Generation failed", "kriti-ai");
      }

      return __("Generation #", "kriti-ai") + job.id;
    }

    function renderDashboardRecent(jobs) {
      if (!recentBody) {
        return;
      }

      if (!jobs || !jobs.length) {
        recentBody.innerHTML =
          '<tr><td class="p-4 text-kriti-ai-slate-gray" colspan="4">' +
          api.esc(__("No recent activity yet.", "kriti-ai")) +
          "</td></tr>";
        return;
      }

      recentBody.innerHTML = jobs
        .slice(0, 5)
        .map(function (job) {
          var type = dashboardActivityType(job.job_type);
          var rowClass =
            job.status === "pending" || job.status === "processing"
              ? "hover:bg-kriti-ai-surface-container-low/50 transition-colors bg-kriti-ai-indigo-wash/30"
              : "hover:bg-kriti-ai-surface-container-low/50 transition-colors";

          return (
            '<tr class="' +
            rowClass +
            '">' +
            '<td class="p-4 font-medium text-kriti-ai-on-surface">' +
            api.esc(dashboardActivityTitle(job)) +
            "</td>" +
            '<td class="p-4 text-kriti-ai-slate-gray"><span class="inline-flex items-center gap-2"><span class="material-symbols-outlined text-[16px]">' +
            api.esc(type[0]) +
            "</span>" +
            api.esc(type[1]) +
            "</span></td>" +
            '<td class="p-4">' +
            statusBadge(job.status || "pending") +
            "</td>" +
            '<td class="p-4 text-kriti-ai-slate-gray">' +
            api.esc(humanTime(job.created_at)) +
            "</td>" +
            "</tr>"
          );
        })
        .join("");
    }

    function loadDashboardRecent() {
      if (!recentBody) {
        return;
      }

      api
        .post("kriti_ai_jobs_recent", { limit: 5 })
        .then(function (jobs) {
          renderDashboardRecent(jobs || []);
        })
        .catch(function () {
          recentBody.innerHTML =
            '<tr><td class="p-4 text-kriti-ai-slate-gray" colspan="4">' +
            api.esc(__("Unable to load recent activity.", "kriti-ai")) +
            "</td></tr>";
        });
    }

    function renderTopMetrics(totals) {
      var values = totals || {};

      ["articles", "images", "audio", "videos"].forEach(function (key) {
        var valueEl = document.querySelector(
          '[data-kriti-ai-kpi="' + key + '"]',
        );
        var trendEl = document.querySelector(
          '[data-kriti-ai-kpi-trend="' + key + '"]',
        );
        var value = Number(values[key] || 0);

        if (valueEl) {
          valueEl.textContent = api.fmt(value);
        }
        if (trendEl) {
          trendEl.textContent = value
            ? __("Generated", "kriti-ai")
            : __("No items", "kriti-ai");
        }
      });
    }

    function formatDuration(ms) {
      if (!ms) {
        return "—";
      }

      const totalSeconds = ms / 1000;

      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;

      if (hours > 0) {
        return `${hours}h ${minutes}m ${seconds.toFixed(2)}s`;
      }

      if (minutes > 0) {
        return `${minutes}m ${seconds.toFixed(2)}s`;
      }

      return `${seconds.toFixed(2)}s`;
    }

    function renderCards(summary) {
      var items = [
        [__("Requests", "kriti-ai"), api.fmt(summary.requests)],
        [__("Success rate", "kriti-ai"), summary.success_rate + "%"],
        [__("Tokens in", "kriti-ai"), api.fmt(summary.tokens_in)],
        [__("Tokens out", "kriti-ai"), api.fmt(summary.tokens_out)],
        // [__("Est. cost", "kriti-ai"), "$" + Number(summary.costs).toFixed(4)],
        [
          __("Avg time", "kriti-ai"),
          summary.avg_duration ? formatDuration(summary.avg_duration) : "—",
        ],
      ];

      cards.innerHTML = items
        .map(function (item) {
          return (
            '<div class="kriti-ai-card"><span class="kriti-ai-card-label">' +
            api.esc(item[0]) +
            "</span>" +
            '<span class="kriti-ai-card-value">' +
            api.esc(item[1]) +
            "</span></div>"
          );
        })
        .join("");
    }

    function renderApiUsage(summary) {
      if (!apiUsageFill || !apiUsageText) {
        return;
      }

      var requests = Number(summary.requests || 0);
      var successRate = requests ? Number(summary.success_rate || 0) : 0;
      var width = Math.max(0, Math.min(100, successRate));
      var cost = Number(summary.cost || 0).toFixed(4);

      apiUsageFill.style.width = width + "%";

      if (apiUsageBar) {
        apiUsageBar.setAttribute("aria-valuenow", String(Math.round(width)));
      }

      if (!requests) {
        apiUsageText.textContent = __(
          "No API requests in this period.",
          "kriti-ai",
        );
        return;
      }

      apiUsageText.textContent =
        api.fmt(requests) +
        " " +
        __("requests", "kriti-ai") +
        " · " +
        successRate +
        "% " +
        __("successful", "kriti-ai") +
        " · $" +
        cost +
        " " +
        __("est. cost", "kriti-ai");
    }

    function renderChart(daily) {
      if (!daily.length) {
        chartBox.innerHTML =
          "<p>" + api.esc(__("No data yet.", "kriti-ai")) + "</p>";
        return;
      }

      var max = Math.max.apply(
        null,
        daily.map(function (d) {
          return d.requests;
        }),
      );
      max = Math.max(max, 1);

      var bars = daily
        .map(function (d) {
          var height = Math.max(4, Math.round((d.requests / max) * 160));
          return (
            '<div class="kriti-ai-bar-wrap">' +
            '<div class="kriti-ai-bar" style="height:' +
            height +
            'px" title="' +
            api.esc(d.day) +
            ": " +
            d.requests +
            ' requests"></div>' +
            '<span class="kriti-ai-bar-label">' +
            api.esc(d.day.slice(5)) +
            "</span>" +
            "</div>"
          );
        })
        .join("");

      chartBox.innerHTML = '<div class="kriti-ai-chart">' + bars + "</div>";
    }

    function renderDraft(draftPublished) {
      if (!draftBox) {
        return;
      }

      var counts = draftPublished || {};
      var draft = Number(counts.draft || 0);
      var published = Number(counts.published || 0);
      var total = draft + published;

      if (!total) {
        draftBox.innerHTML =
          '<p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray">' +
          api.esc(
            __("No generated drafts or published items yet.", "kriti-ai"),
          ) +
          "</p>";
        return;
      }

      var draftPct = Math.round((100 * draft) / total);
      var publishedPct = 100 - draftPct;

      draftBox.innerHTML =
        '<div class="kriti-ai-bars">' +
        '<div class="flex items-center justify-between mb-3"><span class="font-kriti-ai-label-md text-kriti-ai-label-md text-kriti-ai-slate-gray">' +
        api.esc(__("Total generated", "kriti-ai")) +
        "</span><strong>" +
        api.fmt(total) +
        "</strong></div>" +
        '<div class="kriti-ai-bar-row"><span>' +
        api.esc(__("Draft", "kriti-ai")) +
        '</span><div class="kriti-ai-track"><div class="kriti-ai-fill kriti-ai-fill-draft" style="width:' +
        draftPct +
        '%"></div></div><strong>' +
        api.fmt(draft) +
        ' <span class="text-kriti-ai-slate-gray font-normal">(' +
        draftPct +
        "%)</span>" +
        "</strong></div>" +
        '<div class="kriti-ai-bar-row"><span>' +
        api.esc(__("Published", "kriti-ai")) +
        '</span><div class="kriti-ai-track"><div class="kriti-ai-fill kriti-ai-fill-published" style="width:' +
        publishedPct +
        '%"></div></div><strong>' +
        api.fmt(published) +
        ' <span class="text-kriti-ai-slate-gray font-normal">(' +
        publishedPct +
        "%)</span>" +
        "</strong></div>" +
        "</div>";
    }

    function connectedModelInitials(label) {
      return String(label || "AI")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(function (part) {
          return part.charAt(0).toUpperCase();
        })
        .join("");
    }

    function connectedModelStatus(model) {
      if (model.status === "ready") {
        return {
          label: __("Ready", "kriti-ai"),
          badge: "bg-emerald-100 text-emerald-700",
          item: "",
          avatar: "bg-emerald-50 text-emerald-600 border-emerald-100",
        };
      }

      if (model.status === "needs_key") {
        return {
          label: __("Needs key", "kriti-ai"),
          badge: "bg-amber-100 text-amber-800",
          item: "",
          avatar: "bg-amber-50 text-amber-700 border-amber-100",
        };
      }

      return {
        label: __("Disabled", "kriti-ai"),
        badge: "bg-slate-200 text-slate-600",
        item: "opacity-70 grayscale",
        avatar: "bg-slate-100 text-slate-500 border-slate-200",
      };
    }

    function renderConnectedModels(models) {
      if (!connectedModels) {
        return;
      }

      if (!models || !models.length) {
        connectedModels.innerHTML =
          '<p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray">' +
          api.esc(__("No providers configured yet.", "kriti-ai")) +
          "</p>";
        return;
      }

      connectedModels.innerHTML = models
        .slice(0, 8)
        .map(function (model) {
          var status = connectedModelStatus(model);
          var capabilities = (model.capabilities || [])
            .map(function (capability) {
              return capability.charAt(0).toUpperCase() + capability.slice(1);
            })
            .join(", ");

          return (
            '<div class="flex items-center justify-between p-3 rounded-lg bg-kriti-ai-surface-container-lowest border border-kriti-ai-outline-variant/30 ' +
            status.item +
            '">' +
            '<div class="flex items-center gap-3 min-w-0">' +
            '<div class="h-8 w-8 rounded flex items-center justify-center font-bold text-xs border shrink-0 ' +
            status.avatar +
            '">' +
            api.esc(connectedModelInitials(model.label)) +
            "</div>" +
            '<div class="min-w-0">' +
            '<p class="font-kriti-ai-body-md text-kriti-ai-body-md font-medium text-kriti-ai-on-surface leading-tight truncate">' +
            api.esc(model.label) +
            "</p>" +
            '<p class="font-kriti-ai-label-md text-[11px] text-kriti-ai-slate-gray truncate">' +
            api.esc(model.model || __("Provider default", "kriti-ai")) +
            "</p>" +
            '<p class="font-kriti-ai-label-md text-[10px] text-kriti-ai-slate-gray truncate">' +
            api.esc(capabilities || __("Provider", "kriti-ai")) +
            "</p>" +
            "</div>" +
            "</div>" +
            '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0 ' +
            status.badge +
            '">' +
            api.esc(status.label) +
            "</span>" +
            "</div>"
          );
        })
        .join("");
    }

    function renderProviderTable(rows) {
      var body = providerTable.querySelector("tbody");

      if (!rows.length) {
        body.innerHTML =
          '<tr><td colspan="6">' +
          api.esc(__("No usage yet.", "kriti-ai")) +
          "</td></tr>";
        return;
      }

      body.innerHTML = rows
        .map(function (row) {
          return (
            "<tr>" +
            "<td>" +
            api.esc(row.provider) +
            "</td>" +
            "<td>" +
            api.fmt(row.requests) +
            "</td>" +
            "<td>" +
            api.fmt(row.success) +
            "</td>" +
            "<td>" +
            api.fmt(row.tokens_in) +
            "</td>" +
            "<td>" +
            api.fmt(row.tokens_out) +
            "</td>" +
            "<td>$" +
            Number(row.cost).toFixed(4) +
            "</td>" +
            "</tr>"
          );
        })
        .join("");
    }

    function load() {
      if (volumeDays) {
        volumeDays.textContent = daysSelect.value;
      }

      api
        .post("kriti_ai_dashboard", { days: daysSelect.value })
        .then(function (data) {
          renderTopMetrics(data.generated_totals);
          renderCards(data.summary);
          renderApiUsage(data.summary);
          renderChart(data.daily);
          renderDraft(data.draft_published);
          renderConnectedModels(data.connected_models);
          renderProviderTable(data.by_provider);
        })
        .catch(function (err) {
          cards.innerHTML =
            '<div class="notice notice-error"><p>' +
            api.esc(err.message) +
            "</p></div>";
        });
    }

    refreshBtn.addEventListener("click", function () {
      load();
      loadDashboardRecent();
    });
    daysSelect.addEventListener("change", load);

    load();
    loadDashboardRecent();
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (document.getElementById("kriti-ai-generate-form")) {
      initGenerate();
    }
    if (document.getElementById("kriti-ai-queue-table")) {
      initQueue();
    }
    if (document.body.classList.contains("post-type-kriti_ai_media")) {
      initMedia();
    }
    if (document.getElementById("kriti-ai-metric-cards")) {
      initDashboard();
    }
  });
})();

jQuery(document).ready(function ($) {
  /**
   * Content Type → Panel mapping
   */
  var contentTypeMap = {
    article: "content",
    audio: "audio",
    video: "video",
  };

  /**
   * Switch content panel
   */
  function switchContentPanel(contentType) {
    var panelName = contentTypeMap[contentType];

    if (!panelName) {
      return;
    }

    // Hide all panels
    $(".kriti-ai-content-panel").addClass("hidden");

    // Show selected panel
    $(
      '.kriti-ai-content-panel[data-content-panel="' + panelName + '"]',
    ).removeClass("hidden");

    // Store current selection
    $("#kriti-ai-content-panels").attr("data-content-type", contentType);
    $("#kriti-ai-content-panels").attr("data-panel", panelName);
  }

  /**
   * Content type change
   */
  $('input[name="content_type"]').on("change", function () {
    var checked = $('input[name="content_type"]:checked');
    var contentType = checked.length ? checked.last().val() : "article";
    switchContentPanel(contentType);
  });

  /**
   * Initialize on page load
   */
  var checkedContentTypes = $('input[name="content_type"]:checked');
  var selectedContentType = checkedContentTypes.length
    ? checkedContentTypes.last().val()
    : "article";

  if (selectedContentType) {
    switchContentPanel(selectedContentType);
  }
});

(function () {
  var itemType = document.getElementById("kriti_ai_meta_item_type");
  var rows = document.querySelectorAll("[data-kriti-ai-text-setting]");

  function toggleTextSettings() {
    var show = !itemType.value || itemType.value === "content";

    rows.forEach(function (row) {
      row.hidden = !show;
      row.querySelectorAll("input, select, textarea").forEach(function (input) {
        input.disabled = !show;
      });
    });
  }

  if (itemType) {
    itemType.addEventListener("change", toggleTextSettings);
    toggleTextSettings();
  }
})();
