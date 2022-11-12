<div class="section blog_blogChange">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <p>{lang}wcf.acp.uzbot.type.description.notifyOnly.userCondition{/lang}</p>
</div>

<div class="section blog_entryChange">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <p>{lang}wcf.acp.uzbot.type.description.notifyOnly.userCondition{/lang}</p>
</div>

<div class="section blog_blogNew">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <p>{lang}wcf.acp.uzbot.type.description.notifyOnly.userCondition{/lang}</p>
</div>

<div class="section blog_entryNew">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <p>{lang}wcf.acp.uzbot.type.description.notifyOnly.userCondition{/lang}</p>
</div>

<div class="section blog_statistics">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <p>{lang}wcf.acp.uzbot.type.description.notifyOnly{/lang}</p>
</div>

<div class="section blog_entryCount">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <dl>
        <dt>{lang}wcf.acp.uzbot.blog.entryCount.action{/lang}</dt>
        <dd>
            <label><input type="radio" name="blogEntryCountAction" value="entryTotal"{if $blogEntryCountAction == 'entryTotal'} checked{/if} /> {lang}wcf.acp.uzbot.blog.entryCount.entryTotal{/lang}</label>
            <label><input type="radio" name="blogEntryCountAction" value="entryX"{if $blogEntryCountAction == 'entryX'} checked{/if} /> {lang}wcf.acp.uzbot.blog.entryCount.entryX{/lang}</label>
            <label><input type="radio" name="blogEntryCountAction" value="entryTop"{if $blogEntryCountAction == 'entryTop'} checked{/if} /> {lang}wcf.acp.uzbot.blog.entryCount.entryTop{/lang}</label>
        </dd>
    </dl>
</div>

<div class="section blog_topBlogger">
    <header class="sectionHeader">
        <h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
    </header>

    <dl>
        <dt><label for="topBloggerCount">{lang}wcf.acp.uzbot.blog.topBlogger.count{/lang}</label></dt>
        <dd>
            <input type="number" name="topBloggerCount" id="topBloggerCount" value="{$topBloggerCount}" class="small" min="0" max="100" />
        </dd>
    </dl>

    <dl>
        <dt>{lang}wcf.acp.uzbot.blog.topBlogger.interval{/lang}</dt>
        <dd>
            <label><input type="radio" name="topBloggerInterval" value="1"{if $topBloggerInterval == 1} checked{/if} /> {lang}wcf.acp.uzbot.blog.topBlogger.interval.week{/lang}</label>
            <label><input type="radio" name="topBloggerInterval" value="2"{if $topBloggerInterval == 2} checked{/if} /> {lang}wcf.acp.uzbot.blog.topBlogger.interval.month{/lang}</label>
            <label><input type="radio" name="topBloggerInterval" value="3"{if $topBloggerInterval == 3} checked{/if} /> {lang}wcf.acp.uzbot.blog.topBlogger.interval.quarter{/lang}</label>
        </dd>
    </dl>

</div>
