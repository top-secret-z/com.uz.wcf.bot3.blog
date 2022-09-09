<div class="section notifyBlogSettings">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.notify.blog.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'blogCategoryIDs'} class="formError"{/if}>
		<dt><label for="blogCategoryIDs">{lang}wcf.acp.uzbot.notify.blog.categoryIDs{/lang}</label></dt>
		<dd></dd>
	</dl>
	
	{if !$flexibleBlogCategoryList|isset}{assign var=flexibleBlogCategoryList value=$blogCategoryList}{/if}
	{if !$flexibleBlogCategoryListName|isset}{assign var=flexibleBlogCategoryListName value='blogCategoryIDs'}{/if}
	{if !$flexibleBlogCategoryListID|isset}{assign var=flexibleBlogCategoryListID value='flexibleBlogCategoryList'}{/if}
	{if !$flexibleBlogCategoryListSelectedIDs|isset}{assign var=flexibleBlogCategoryListSelectedIDs value=$blogCategoryIDs}{/if}
	<ol class="flexibleCategoryList" id="{$flexibleBlogCategoryListID}">
		{foreach from=$flexibleBlogCategoryList item=categoryItem}
			<li>
				<div class="containerHeadline">
					<h3><label{if $categoryItem->getDescription()} class="jsTooltip" title="{$categoryItem->getDescription()}"{/if}><input type="checkbox" name="{$flexibleBlogCategoryListName}[]" value="{@$categoryItem->categoryID}" class="jsCategory"{if $categoryItem->categoryID|in_array:$flexibleBlogCategoryListSelectedIDs} checked{/if}> {$categoryItem->getTitle()}</label></h3>
				</div>
				
				{if $categoryItem->hasChildren()}
					<ol>
						{foreach from=$categoryItem item=subCategoryItem}
							<li>
								<label{if $subCategoryItem->getDescription()} class="jsTooltip" title="{$subCategoryItem->getDescription()}"{/if} style="font-size: 1rem;"><input type="checkbox" name="{$flexibleBlogCategoryListName}[]" value="{@$subCategoryItem->categoryID}" class="jsChildCategory"{if $subCategoryItem->categoryID|in_array:$flexibleBlogCategoryListSelectedIDs} checked{/if}> {$subCategoryItem->getTitle()}</label>
								
								{if $subCategoryItem->hasChildren()}
									<ol>
										{foreach from=$subCategoryItem item=subSubCategoryItem}
											<li>
												<label{if $subSubCategoryItem->getDescription()} class="jsTooltip" title="{$subSubCategoryItem->getDescription()}"{/if}><input type="checkbox" name="{$flexibleBlogCategoryListName}[]" value="{@$subSubCategoryItem->categoryID}" class="jsSubChildCategory"{if $subSubCategoryItem->categoryID|in_array:$flexibleBlogCategoryListSelectedIDs} checked{/if}> {$subSubCategoryItem->getTitle()}</label>
											</li>
										{/foreach}
									</ol>
								{/if}
							</li>
						{/foreach}
					</ol>
				{/if}
			</li>
		{/foreach}
	</ol>
	
	{if $errorField == 'blogCategoryIDs'}
		<small class="innerError">
			{lang}wcf.acp.uzbot.notify.blog.categoryIDs.error.{@$errorType}{/lang}
		</small>
	{/if}
	
	<dl{if $errorField == 'blogID'} class="formError"{/if}>
		<dt><label for="blogID">{lang}wcf.acp.uzbot.notify.blog.blogID{/lang}</label></dt>
		<dd>
			<select name="blogID" id="blogID">
				<option value="0"{if $blogID == 0} selected{/if}>0 - {lang}wcf.acp.uzbot.notify.blog.automatic{/lang}</option>
				
				{foreach from=$availableBlogs item=availableBlog}
					<option value="{@$availableBlog->blogID}"{if $blogID == $availableBlog->blogID} selected{/if}>{$availableBlog->blogID} - {$availableBlog->title}</option>
				{/foreach}
			</select>
			{if $errorField == 'blogID'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.notify.blog.blogID.error.{@$errorType}{/lang}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.notify.blog.status{/lang}</dt>
		<dd>
			<label><input name="blogEnableComments" type="checkbox" value="1"{if $blogEnableComments} checked{/if}> {lang}wcf.acp.uzbot.notify.blog.status.enableComments{/lang}</label>
			<label><input name="blogIsDisabled" type="checkbox" value="1"{if $blogIsDisabled} checked{/if}> {lang}wcf.acp.uzbot.notify.blog.status.isDisabled{/lang}</label>
			<label><input name="blogIsDraft" type="checkbox" value="1"{if $blogIsDraft} checked{/if}> {lang}wcf.acp.uzbot.notify.blog.status.isDraft{/lang}</label>
			<label><input name="blogIsFeatured" type="checkbox" value="1"{if $blogIsFeatured} checked{/if}> {lang}wcf.acp.uzbot.notify.blog.status.isFeatured{/lang}</label>
		</dd>
	</dl>
</div>
