package main

import (
	"flag"
	"fmt"
	kassiscore "github.com/nakamura-akifumi/kassis"
)

func main() {

	fmt.Printf("kassis configrator. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	generateDefaultConfigSet := flag.Bool("generate-default-configset", false, "generate default configset to current directory")
	setupSolrSchema := flag.Bool("setup-solr", false, "setup solr schema")
	flag.Parse()

	fmt.Println("generateDefaultConfigSet:", *generateDefaultConfigSet)
	if *generateDefaultConfigSet == true {
		kassiscore.GenerateDefaultConfigSet()
	} else if *setupSolrSchema == true {
		kassiscore.SetupSolr()
	} else {
		kassiscore.CheckConfigAndConnections()
	}

}