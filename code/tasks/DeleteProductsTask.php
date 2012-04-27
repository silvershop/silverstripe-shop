<?php
/**
 * Remove all products and categories from the site.
 */
class DeleteProductsTask extends BuildTask{
	
	protected $title = "Delete Products";
	protected $description = "Removes all Products and Categories from the database.";
	
	function run($request){
		
		 //TODO: include decendant clases..incase some subclassing has been done somewhere
		
		if($allorders = DataObject::get('Order')){
			foreach($allorders as $order){
				$order->delete();
				$order->destroy();
			}
		}
		
		if($allproducts = DataObject::get('Product')){
			foreach($allproducts as $product){
				$product->deleteFromStage('Live');
				$product->deleteFromStage('Stage');
				$product->destroy();
				//TODO: remove versions		
			}
		}
		
		if($allcategories = DataObject::get('ProductCategory')){
			foreach($allcategories as $category){
				$category->deleteFromStage('Live');
				$category->deleteFromStage('Stage');
				$category->destroy();
				//TODO: remove versions
			}
		}
		
		//TODO: use TRUNCATE instead?
		
		$basetables = array(
			'Product',
				'Product_Live','Product_versions','Product_ProductCategories','Product_OrderItem','Product_VariationAttributeTypes',
			'ProductVariation',
				'ProductVariation_AttributeValues','ProductVariation_OrderItem','ProductVariation_versions',
			'ProductAttributeType','ProductAttributeValue'
		);
		
		foreach($basetables as $table){
			if(!(ClassInfo::hasTable($table)))
				continue;
			foreach(ClassInfo::subclassesFor($table) as $key => $class){
				if(ClassInfo::hasTable($class)){
					DB::query("DELETE FROM \"$class\" WHERE 1;");
					echo "<p>Deleting all $class</p>";
				}
			}
		}
		
		//partial empty queries
		echo "<p>Deleting all SiteTree</p>";
		DB::query("DELETE FROM \"SiteTree\" WHERE ClassName = 'Product';");//SiteTree
		DB::query("DELETE FROM \"SiteTree_Live\" WHERE ClassName = 'Product';");//SiteTree
		DB::query("DELETE FROM \"SiteTree_versions\" WHERE ClassName = 'Product';");//SiteTree
		
	}
	
}